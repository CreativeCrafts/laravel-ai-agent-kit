<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\QueuedPipelineDispatcher;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\Tool;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolAuthorizer;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fakes\TestQueuedPipelineDefinition;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\InvalidToolInputException;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\ToolUnauthorizedException;
use CreativeCrafts\LaravelAiAgentKit\Tools\InMemoryToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Vector\DatabaseVectorStore;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorDocument;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorSearchQuery;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

it('rejects custom tool input with missing nested required fields before execution', function (): void {
    $registry = new InMemoryToolRegistry(allowAllToolAuthorizer());
    $tool = customerLookupTool();
    $registry->register($tool);

    expect(fn () => $registry->execute('customer.lookup', [
        'customer' => [
            'name' => 'Prince',
        ],
        'items' => ['invoice'],
        'status' => 'open',
        'note' => null,
    ]))->toThrow(InvalidToolInputException::class, 'customer.email');

    expect($tool->executions)->toBe(0);
});

it('rejects custom tool input with nested additional properties before execution', function (): void {
    $registry = new InMemoryToolRegistry(allowAllToolAuthorizer());
    $tool = customerLookupTool();
    $registry->register($tool);

    expect(fn () => $registry->execute('customer.lookup', [
        'customer' => [
            'email' => 'prince@example.com',
            'unexpected' => true,
        ],
        'items' => ['invoice'],
        'status' => 'open',
        'note' => null,
    ]))->toThrow(InvalidToolInputException::class, 'customer.unexpected');

    expect($tool->executions)->toBe(0);
});

it('rejects custom tool input with invalid array item types before execution', function (): void {
    $registry = new InMemoryToolRegistry(allowAllToolAuthorizer());
    $tool = customerLookupTool();
    $registry->register($tool);

    expect(fn () => $registry->execute('customer.lookup', [
        'customer' => [
            'email' => 'prince@example.com',
        ],
        'items' => ['invoice', 123],
        'status' => 'open',
        'note' => null,
    ]))->toThrow(InvalidToolInputException::class, 'items[1]');

    expect($tool->executions)->toBe(0);
});

it('enforces nullable and enum constraints for custom tool input', function (): void {
    $registry = new InMemoryToolRegistry(allowAllToolAuthorizer());
    $tool = customerLookupTool();
    $registry->register($tool);

    $result = $registry->execute('customer.lookup', [
        'customer' => [
            'email' => 'prince@example.com',
        ],
        'items' => ['invoice'],
        'status' => 'open',
        'note' => null,
    ]);

    expect($result)->toBe(['ok' => true])
        ->and($tool->executions)->toBe(1);

    expect(fn () => $registry->execute('customer.lookup', [
        'customer' => [
            'email' => 'prince@example.com',
        ],
        'items' => ['invoice'],
        'status' => 'pending',
        'note' => null,
    ]))->toThrow(InvalidToolInputException::class, 'status');
});

it('preserves default-deny custom tool authorization after recursive validation', function (): void {
    $registry = new InMemoryToolRegistry();
    $tool = customerLookupTool();
    $registry->register($tool);

    expect(fn () => $registry->execute('customer.lookup', [
        'customer' => [
            'email' => 'prince@example.com',
        ],
        'items' => ['invoice'],
        'status' => 'open',
        'note' => null,
    ]))->toThrow(ToolUnauthorizedException::class, 'customer.lookup');

    expect($tool->executions)->toBe(0);
});

it('database vector upsert replaces existing rows atomically without changing created_at', function (): void {
    Schema::dropIfExists('ai_agent_vector_documents');

    /** @var Migration $migration */
    $migration = require __DIR__.'/../database/migrations/create_ai_agent_vector_documents_table.php.stub';
    $migration->up();

    $store = new DatabaseVectorStore(DB::connection('testing'), 'ai_agent_vector_documents');

    $store->upsert('support', [
        new VectorDocument(
            id: 'doc-atomic',
            embedding: [1.0, 0.0],
            metadata: ['version' => 1],
        ),
    ]);

    $createdAt = DB::table('ai_agent_vector_documents')
        ->where('namespace', 'support')
        ->where('document_id', 'doc-atomic')
        ->value('created_at');

    $store->upsert('support', [
        new VectorDocument(
            id: 'doc-atomic',
            embedding: [0.0, 1.0],
            metadata: ['version' => 2],
        ),
    ]);

    $rows = DB::table('ai_agent_vector_documents')
        ->where('namespace', 'support')
        ->where('document_id', 'doc-atomic')
        ->get();

    $matches = $store->search(
        'support',
        new VectorSearchQuery(
            embedding: [0.0, 1.0],
            limit: 1,
        ),
    );

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->created_at)->toBe($createdAt)
        ->and($matches)->toHaveCount(1)
        ->and($matches[0]->metadata)->toBe(['version' => 2]);
});

it('database vector upsert treats empty batches as no-ops', function (): void {
    Schema::dropIfExists('ai_agent_vector_documents');

    /** @var Migration $migration */
    $migration = require __DIR__.'/../database/migrations/create_ai_agent_vector_documents_table.php.stub';
    $migration->up();

    $store = new DatabaseVectorStore(DB::connection('testing'), 'ai_agent_vector_documents');

    $store->upsert('support', []);

    expect(DB::table('ai_agent_vector_documents')->count())->toBe(0);
});

it('enforces production queued pipeline payload guard when debug is disabled', function (): void {
    Queue::fake();

    config()->set('app.debug', false);
    config()->set('ai-agent-kit.pipeline.queued.payload_guard', true);
    config()->set('ai-agent-kit.pipeline.queued.debug_payload_guard', false);
    config()->set('ai-agent-kit.pipeline.queued.max_serialized_job_bytes', 256);

    /** @var QueuedPipelineDispatcher $dispatcher */
    $dispatcher = app(QueuedPipelineDispatcher::class);

    expect(fn () => $dispatcher->dispatch(
        pipelineDefinition: TestQueuedPipelineDefinition::class,
        context: new RunContext(
            runId: 'run-production-payload-guard',
            input: ['blob' => str_repeat('x', 400)],
        ),
    ))->toThrow(RuntimeException::class, 'docs/pipelines-and-queues.md');
});

it('does not run debug queued pipeline payload guard when debug is disabled and production guard is off', function (): void {
    Queue::fake();

    config()->set('app.debug', false);
    config()->set('ai-agent-kit.pipeline.queued.payload_guard', false);
    config()->set('ai-agent-kit.pipeline.queued.debug_payload_guard', true);
    config()->set('ai-agent-kit.pipeline.queued.max_serialized_job_bytes', 256);

    /** @var QueuedPipelineDispatcher $dispatcher */
    $dispatcher = app(QueuedPipelineDispatcher::class);

    $dispatcher->dispatch(
        pipelineDefinition: TestQueuedPipelineDefinition::class,
        context: new RunContext(
            runId: 'run-debug-guard-disabled',
            input: ['blob' => str_repeat('x', 400)],
        ),
    );

    Queue::assertPushed(\CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Jobs\RunQueuedPipelineJob::class);
});

function allowAllToolAuthorizer(): ToolAuthorizer
{
    return new class () implements ToolAuthorizer {
        public function authorizeCustomTool(Tool $tool, array $input): bool
        {
            return true;
        }

        public function authorizeProviderTool(string $providerToolName): bool
        {
            return true;
        }
    };
}

function customerLookupTool(): Tool
{
    return new class () implements Tool {
        public int $executions = 0;

        public function name(): string
        {
            return 'customer.lookup';
        }

        public function inputSchema(): array
        {
            return [
                'type' => 'object',
                'properties' => [
                    'customer' => [
                        'type' => 'object',
                        'properties' => [
                            'email' => ['type' => 'string'],
                            'name' => ['type' => 'string', 'nullable' => true],
                        ],
                        'required' => ['email'],
                        'additionalProperties' => false,
                    ],
                    'items' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                    'status' => [
                        'type' => 'string',
                        'enum' => ['open', 'closed'],
                    ],
                    'note' => [
                        'type' => 'string',
                        'nullable' => true,
                    ],
                ],
                'required' => ['customer', 'items', 'status'],
                'additionalProperties' => false,
            ];
        }

        public function execute(array $input): array
        {
            $this->executions++;

            return ['ok' => true];
        }
    };
}
