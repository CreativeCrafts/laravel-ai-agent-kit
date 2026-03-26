# Laravel AI Agent Kit

[![Latest Version on Packagist](https://img.shields.io/packagist/v/creativecrafts/laravel-ai-agent-kit.svg?style=flat-square)](https://packagist.org/packages/creativecrafts/laravel-ai-agent-kit)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/creativecrafts/laravel-ai-agent-kit/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/creativecrafts/laravel-ai-agent-kit/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/creativecrafts/laravel-ai-agent-kit/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/creativecrafts/laravel-ai-agent-kit/actions?query=workflow%3A%22Fix+PHP+code+style+issues%22+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/creativecrafts/laravel-ai-agent-kit.svg?style=flat-square)](https://packagist.org/packages/creativecrafts/laravel-ai-agent-kit)

Laravel AI Agent Kit is a Laravel package that delivers a structured agent-workflow toolkit built on top of the official Laravel AI SDK. It provides provider abstraction, pipeline orchestration,
queued execution, and package foundations for building AI-powered application flows safely and predictably.

## Installation

Install the package with Composer:

~~~bash
composer require creativecrafts/laravel-ai-agent-kit
~~~

Laravel AI Agent Kit requires the official Laravel AI SDK at runtime. The package now declares `laravel/ai` as a Composer dependency, so Composer will install the SDK automatically when you require
this package.

Publish the Laravel AI SDK configuration and migrations first:

~~~bash
php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
~~~

Then publish and run this package's migrations:

~~~bash
php artisan vendor:publish --tag="ai-agent-kit-migrations"
php artisan migrate
~~~

Publish this package's configuration file:

~~~bash
php artisan vendor:publish --tag="ai-agent-kit-config"
~~~

Optionally, publish the views:

~~~bash
php artisan vendor:publish --tag="ai-agent-kit-views"
~~~

## Configuration

The package validates its configuration during boot by default.

At least one enabled provider must exist, `default_provider` must reference an enabled configured provider, and `failover_order` must include the default provider.

The default memory driver is `in_memory`. That default is explicit, non-persistent, and safe for tests, local development, and ephemeral runs. Switch `memory.default_driver` to `database` when you
want encrypted persistent storage and retention-based purging, or to `redis` when you need shared ephemeral memory across workers.

Retry and circuit breaker resilience settings are configured explicitly under `resilience`. Retry policy evaluation remains bounded by `budgets.max_retries_per_step`, and the circuit breaker exposes
clear `closed`, `open`, and `half_open` semantics with configurable thresholds and reset timing.

Pipeline lifecycle and failover telemetry are emitted through Laravel events with redacted defaults. Event payloads expose safe metadata such as run identifiers, provider names, step classes, counts,
and key lists rather than raw prompt, input, metadata, or provider option values by default.

Example configuration:

~~~php
return [
    'validation' => [
        'enabled' => true,
    ],

    'providers' => [
        'null' => [
            'driver' => 'null',
            'enabled' => true,
            'options' => [],
        ],
    ],

    'default_provider' => 'null',

    'failover_order' => ['null'],

    'budgets' => [
        'max_steps' => 20,
        'max_tool_calls' => 50,
        'max_retries_per_step' => 2,
        'max_total_timeout_seconds' => 120,
        'max_tokens' => null,
        'max_cost_usd' => null,
    ],

    'resilience' => [
        'retry' => [
            'enabled' => true,
            'max_attempts' => 3,
            'backoff' => [
                'strategy' => 'exponential',
                'base_delay_ms' => 250,
                'max_delay_ms' => 2000,
                'multiplier' => 2.0,
            ],
        ],
        'circuit_breaker' => [
            'enabled' => true,
            'failure_threshold' => 3,
            'reset_timeout_seconds' => 60,
            'half_open_success_threshold' => 1,
        ],
    ],

    'memory' => [
        'default_driver' => 'in_memory',

        'in_memory' => [
            'retention_days' => null,
        ],

        'database' => [
            'connection' => null,
            'conversations_table' => 'ai_agent_conversations',
            'messages_table' => 'ai_agent_conversation_messages',
            'driver_name' => 'database',
            'retention_days' => 30,
            'encrypt_payloads' => true,
        ],

        'redis' => [
            'connection' => null,
            'prefix' => 'ai_agent_memory:',
            'driver_name' => 'redis',
            'retention_days' => 7,
        ],
    ],
];
~~~

## Usage

Resolve the configured provider registry or default provider selector through the container:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;

$registry = app(ProviderRegistry::class);
$selector = app(ProviderSelector::class);

$defaultProvider = $selector->selectDefault();
$provider = $registry->get('null');
~~~

Register first-class agents explicitly through the package agent registry in your application service provider:

~~~php
use App\Agents\CancellationAgent;
use App\Agents\CustomerSupportAgent;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\AgentRegistry;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function boot(AgentRegistry $agents): void
    {
        $agents->registerMany([
            CustomerSupportAgent::class,
            CancellationAgent::class,
        ]);
    }
}
~~~

Registered agents are resolved through the Laravel container and looked up by the stable agent key returned from their package-owned `AgentDefinition`.

Build and run a synchronous pipeline with typed steps:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineRunner;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineStep;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\PipelineBuilder;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;

$pipeline = PipelineBuilder::make()
    ->addStep(new class implements PipelineStep
    {
        public function handle(RunContext $context): RunContext
        {
            return $context
                ->withStateValue('normalized', true)
                ->incrementStepCount();
        }
    })
    ->build();

$runner = app(PipelineRunner::class);

$result = $runner->run(
    $pipeline,
    new RunContext(
        runId: 'run-001',
        input: ['text' => 'Hello world'],
    ),
);
~~~

Dispatch a queued pipeline using a typed pipeline definition and explicit result handler:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineResultHandler;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineStep;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\QueuedPipelineDefinition;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\QueuedPipelineDispatcher;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Pipeline;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\PipelineBuilder;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\QueueDispatchOptions;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use Throwable;

final class NormalizeTranscriptPipeline implements QueuedPipelineDefinition
{
    public function build(): Pipeline
    {
        return PipelineBuilder::make()
            ->addStep(new class implements PipelineStep
            {
                public function handle(RunContext $context): RunContext
                {
                    return $context
                        ->withStateValue('normalized', true)
                        ->incrementStepCount();
                }
            })
            ->build();
    }
}

final class StorePipelineResult implements PipelineResultHandler
{
    public function handleSuccess(RunContext $context): void
    {
        // Persist or publish the result explicitly.
    }

    public function handleFailure(RunContext $context, Throwable $throwable): void
    {
        // Persist or publish the failure explicitly.
    }
}

$dispatcher = app(QueuedPipelineDispatcher::class);

$dispatcher->dispatch(
    pipelineDefinition: NormalizeTranscriptPipeline::class,
    context: new RunContext(
        runId: 'run-queued-001',
        input: ['text' => 'Hello world'],
    ),
    options: new QueueDispatchOptions(
        connection: 'redis',
        queue: 'ai-pipelines',
        delaySeconds: 5,
        timeoutSeconds: 90,
    ),
    resultHandler: StorePipelineResult::class,
);
~~~

Use the memory contracts through their default non-persistent driver:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationRetentionPurger;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessage;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessageRole;
use CreativeCrafts\LaravelAiAgentKit\Memory\MessageId;

$store = app(ConversationStore::class);

$conversation = new Conversation(
    id: new ConversationId('conv-001'),
    messages: [
        new ConversationMessage(
            id: new MessageId('msg-001'),
            role: ConversationMessageRole::User,
            content: 'Hello world',
        ),
    ],
);

$store->save($conversation);

$loaded = $store->get(new ConversationId('conv-001'));

$purger = app(ConversationRetentionPurger::class);
$purger->purgeExpired();
~~~

Run retention purge operationally through the package command:

~~~bash
php artisan ai:purge:conversations
php artisan ai:purge:conversations --queued --connection=redis --queue=ai-maintenance
~~~

Schedule the purge command from your application scheduler:

~~~php
use Illuminate\Support\Facades\Schedule;

Schedule::command('ai:purge:conversations')->daily();
~~~

Use the default summarizer through the summarization contract:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationSummarizer;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessage;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessageRole;
use CreativeCrafts\LaravelAiAgentKit\Memory\MessageId;
use CreativeCrafts\LaravelAiAgentKit\Memory\SummarizationInput;

$summarizer = app(ConversationSummarizer::class);

$input = new SummarizationInput(
    conversationId: new ConversationId('conv-summary'),
    messages: [
        new ConversationMessage(
            id: new MessageId('msg-001'),
            role: ConversationMessageRole::User,
            content: 'Summarize this exchange.',
        ),
        new ConversationMessage(
            id: new MessageId('msg-002'),
            role: ConversationMessageRole::Assistant,
            content: 'Here is the reply.',
        ),
    ],
    existingSummary: null,
);

$result = $summarizer->summarize($input);
~~~

Render a versioned prompt template through the in-memory prompt repository:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Prompts\PromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Prompts\PromptTemplate;

$repository = app(PromptRepository::class);

$repository->store(
    new PromptTemplate(
        name: 'support.reply',
        version: 'v1',
        content: 'Reply to {{customer_name}} about {{topic}}.',
        variables: ['customer_name', 'topic'],
    ),
);

$rendered = $repository->render('support.reply', 'v1', [
    'customer_name' => 'Taylor',
    'topic' => 'account verification',
]);
~~~

Register a tool explicitly and provide an authorization hook before execution:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\Tool;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolRegistry;

$registry = app(ToolRegistry::class);

$registry->register(new class () implements Tool
{
    public function name(): string
    {
        return 'math.add';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'left' => ['type' => 'integer'],
                'right' => ['type' => 'integer'],
            ],
            'required' => ['left', 'right'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $input): array
    {
        return ['sum' => $input['left'] + $input['right']];
    }
});
~~~

Configure a package-level tool authorizer (defaults to deny-all) through `config/ai-agent-kit.php`:

~~~php
'tools' => [
    'authorizer' => \CreativeCrafts\LaravelAiAgentKit\Tools\DenyAllToolAuthorizer::class,
    'provider_tools' => [
        // ...
    ],
],
~~~

Tool input schema `type: array` accepts both list and associative PHP arrays; use `type: object` when map-only semantics are required.

Generate a tool or prompt scaffold:

~~~bash
php artisan ai:make:tool Support/LookupCustomer
php artisan ai:make:prompt Support.Reply --prompt-version=2.1.0
~~~

Use the vector store contract through a backend-agnostic interface:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Vector\VectorStoreInterface;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorDocument;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorSearchQuery;

/** @var VectorStoreInterface $vectorStore */
$vectorStore = app(VectorStoreInterface::class);

$vectorStore->upsert('support', [
    new VectorDocument(
        id: 'doc-1',
        embedding: [0.12, 0.98, 0.44],
        metadata: ['topic' => 'billing'],
    ),
]);

$results = $vectorStore->search('support', new VectorSearchQuery(
    embedding: [0.10, 0.95, 0.40],
    limit: 5,
));

$vectorStore->delete('support', ['doc-1']);
~~~

The package-owned vector port remains authoritative. `VectorStoreInterface`, `VectorDocument`, `VectorSearchQuery`, `VectorSearchResult`, and the typed vector exceptions are the stable package
boundary for retrieval flows.

SDK-backed retrieval remains an implementation strategy rather than a public contract replacement. Internal adapters may delegate embedding generation, provider-native retrieval execution, or
retrieval orchestration to Laravel AI SDK, but they must continue to accept package-owned vector inputs and return package-owned `VectorSearchResult` collections. SDK types must not leak through
public vector contracts, DTOs, or typed exceptions.

## Testing

Run the test suite:

~~~bash
composer test
~~~

Run static analysis:

~~~bash
composer analyse
~~~

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Godspower Oduose](https://github.com/rockblings)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.