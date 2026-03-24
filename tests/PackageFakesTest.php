<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\FailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Vector\VectorStoreInterface;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessage;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessageRole;
use CreativeCrafts\LaravelAiAgentKit\Memory\MessageId;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeProviderPolicy;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeToolRunner;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeVectorStore;
use CreativeCrafts\LaravelAiAgentKit\Vector\Exceptions\VectorOperationException;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorDocument;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorSearchQuery;

it('provides a fake ai runtime that can be bound into the container deterministically', function () {
    $fake = new FakeAiRuntime([
      new ExecutionResult(
          runId: 'run-fake-001',
          output: 'Fake runtime response',
          provider: 'fake-provider',
          model: 'fake-model',
      ),
    ]);

    app()->instance(AiRuntime::class, $fake);

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);
    $result = $runtime->execute(
        new ExecutionRequest(
            runId: 'run-fake-001',
            prompt: 'Summarize the incident.',
            provider: 'fake-provider',
            model: 'fake-model',
        ),
    );

    expect($runtime)
      ->toBe($fake)
      ->and($result->output)->toBe('Fake runtime response')
      ->and($fake->lastRequest()?->prompt)->toBe('Summarize the incident.')
      ->and(count($fake->requests()))->toBe(1);
});

it('provides a fake provider policy across registry selection and failover contracts', function () {
    $fake = new FakeProviderPolicy(
        providers: [
        new ProviderDefinition('primary', 'null', true, ['region' => 'eu']),
        new ProviderDefinition('backup', 'null', true, ['region' => 'us']),
      ],
        defaultProviderName: 'primary',
        failoverOrder: ['primary', 'backup'],
    );

    app()->instance(ProviderRegistry::class, $fake);
    app()->instance(ProviderSelector::class, $fake);
    app()->instance(FailoverProviderSelector::class, $fake);

    expect(app(ProviderRegistry::class)->get('primary')->options)
      ->toBe(['region' => 'eu'])
      ->and(app(ProviderSelector::class)->selectDefault()->name)->toBe('primary')
      ->and(app(FailoverProviderSelector::class)->nextAfter('primary')?->name)->toBe('backup')
      ->and($fake->selectedDefaults())->toBe(['primary'])
      ->and($fake->failoverLookups())->toBe(['primary']);
});

it('provides a fake tool runner that records executions and supports stubbed tools', function () {
    $fake = (new FakeToolRunner())
      ->stub('math.add', static fn (array $input): array => ['sum' => $input['left'] + $input['right']], [
        'type' => 'object',
        'properties' => [
          'left' => ['type' => 'integer'],
          'right' => ['type' => 'integer'],
        ],
        'required' => ['left', 'right'],
        'additionalProperties' => false,
      ]);

    app()->instance(ToolRegistry::class, $fake);

    $result = app(ToolRegistry::class)->execute('math.add', ['left' => 2, 'right' => 3]);

    expect($result)
      ->toBe(['sum' => 5])
      ->and($fake->lastExecution())
      ->toBe([
        'name' => 'math.add',
        'input' => ['left' => 2, 'right' => 3],
      ]);
});

it('provides a fake conversation store with deterministic retention purging', function () {
    $updatedAt = new DateTimeImmutable('2026-02-01T00:00:00+00:00');
    $conversation = new Conversation(
        id: new ConversationId('conv-fake-memory-001'),
        createdAt: $updatedAt,
        updatedAt: $updatedAt,
        messages: [
        new ConversationMessage(
            id: new MessageId('msg-fake-memory-001'),
            role: ConversationMessageRole::User,
            content: 'Old message',
            createdAt: $updatedAt,
        ),
      ],
    );

    $fake = new FakeConversationStore(retentionDays: 30, conversations: [$conversation]);
    $purged = $fake->purgeExpired(new DateTimeImmutable('2026-03-15T00:00:00+00:00'));

    expect($purged)
      ->toBe(1)
      ->and($fake->find(new ConversationId('conv-fake-memory-001')))->toBeNull()
      ->and($fake->purgeCounts())->toBe([1]);
});

it('provides a fake vector store with deterministic search delete and failure hooks', function () {
    $fake = new FakeVectorStore();

    app()->instance(VectorStoreInterface::class, $fake);

    app(VectorStoreInterface::class)->upsert('support', [
      new VectorDocument(
          id: 'doc-1',
          embedding: [0.5, 0.5],
          metadata: ['topic' => 'billing'],
      ),
      new VectorDocument(
          id: 'doc-2',
          embedding: [0.1, 0.2],
          metadata: ['topic' => 'shipping'],
      ),
    ]);

    $results = app(VectorStoreInterface::class)->search(
        'support',
        new VectorSearchQuery(
            embedding: [1.0, 1.0],
            limit: 1,
            filter: ['topic' => 'billing'],
        ),
    );

    $deleted = app(VectorStoreInterface::class)->delete('support', ['doc-1']);

    expect($results)
      ->toHaveCount(1)
      ->and($results[0]->id)->toBe('doc-1')
      ->and($deleted)->toBe(1)
      ->and($fake->upserts())->toBe([
        ['namespace' => 'support', 'count' => 2],
      ])
      ->and($fake->deletions())->toBe([
        ['namespace' => 'support', 'document_ids' => ['doc-1']],
      ]);

    $fake->failOperation('search');

    expect(fn () => $fake->search('support', new VectorSearchQuery(embedding: [1.0], limit: 1)))
      ->toThrow(VectorOperationException::class, 'search');
});
