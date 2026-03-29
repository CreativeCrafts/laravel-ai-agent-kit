<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\FailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Vector\VectorStoreInterface;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessage;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessageRole;
use CreativeCrafts\LaravelAiAgentKit\Memory\MessageId;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAgentOrchestrator;
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

it('provides a fake agent orchestrator that can model delegation and ownership transfer deterministically', function () {
    $fake = (new FakeAgentOrchestrator())
      ->queueDelegationFlowResult(
          sourceAgent: 'support.agent',
          targetAgent: 'refund.agent',
          handoffSummary: 'Collect refund context and return the resolution summary.',
          finalOutput: [
          'workflow' => 'support_refund',
          'delegated_agent' => 'refund.agent',
        ],
      )
      ->queueTransferredResult(
          sourceAgent: 'triage.agent',
          targetAgent: 'specialist.agent',
          handoffSummary: 'Transfer final ownership to the specialist.',
          finalOutput: [
          'owner' => 'specialist.agent',
        ],
      );

    app()->instance(AgentOrchestrator::class, $fake);

    $delegated = app(AgentOrchestrator::class)->run(
        new OrchestrationRequest(
            entryAgent: 'support.agent',
            task: 'Handle a refund request',
            input: ['subscription_id' => 'sub-001'],
        ),
    );

    $transferred = app(AgentOrchestrator::class)->run(
        new OrchestrationRequest(
            entryAgent: 'triage.agent',
            task: 'Escalate to the specialist',
            input: ['case_id' => 'case-001'],
        ),
    );

    expect($delegated)
      ->toBeInstanceOf(OrchestrationResult::class)
      ->and($delegated->finalAgent)->toBe('support.agent')
      ->and($delegated->trace)->toHaveCount(3)
      ->and($delegated->trace[0]->summary)->toBe('Collect refund context and return the resolution summary.')
      ->and($delegated->trace[1]->parentExecutionId)->toBe($delegated->trace[0]->executionId)
      ->and($delegated->trace[2]->parentExecutionId)->toBe($delegated->trace[1]->executionId)
      ->and($transferred)
      ->toBeInstanceOf(OrchestrationResult::class)
      ->and($transferred->finalAgent)->toBe('specialist.agent')
      ->and($transferred->trace)->toHaveCount(2)
      ->and($transferred->trace[0]->targetAgent)->toBe('specialist.agent')
      ->and($transferred->trace[1]->parentExecutionId)->toBe($transferred->trace[0]->executionId)
      ->and($fake)
      ->toHaveOrchestrationExecutions(2)
      ->and($fake->lastRequest()?->task)
      ->toBe('Escalate to the specialist');
});

it('derives queued orchestration ids from run order across drained queues and reset cycles', function () {
    $fake = new FakeAgentOrchestrator();

    $first = $fake
      ->queueCompletedResult(
          finalAgent: 'support.agent',
          summary: 'Completed support task.',
      )
      ->run(
          new OrchestrationRequest(
              entryAgent: 'support.agent',
              task: 'Handle support request 001',
              input: [],
          ),
      );

    $second = $fake
      ->queueCompletedResult(
          finalAgent: 'billing.agent',
          summary: 'Completed billing task.',
      )
      ->run(
          new OrchestrationRequest(
              entryAgent: 'billing.agent',
              task: 'Handle billing request 002',
              input: [],
          ),
      );

    $delegated = $fake
      ->queueDelegationFlowResult(
          sourceAgent: 'support.agent',
          targetAgent: 'refund.agent',
          handoffSummary: 'Delegate refund handling.',
          finalOutput: ['workflow' => 'support_refund'],
      )
      ->run(
          new OrchestrationRequest(
              entryAgent: 'support.agent',
              task: 'Handle support request 003',
              input: [],
          ),
      );

    $transferred = $fake
      ->queueTransferredResult(
          sourceAgent: 'triage.agent',
          targetAgent: 'specialist.agent',
          handoffSummary: 'Transfer ownership to specialist.',
          finalOutput: ['owner' => 'specialist.agent'],
      )
      ->run(
          new OrchestrationRequest(
              entryAgent: 'triage.agent',
              task: 'Handle support request 004',
              input: [],
          ),
      );

    expect([
      $first->orchestrationId,
      $second->orchestrationId,
      $delegated->orchestrationId,
      $transferred->orchestrationId,
    ])
      ->toBe([
        'fake-orchestration-001',
        'fake-orchestration-002',
        'fake-orchestration-003',
        'fake-orchestration-004',
      ])
      ->and($first->finalExecutionId)->toBe('fake-execution-001')
      ->and($second->finalExecutionId)->toBe('fake-execution-002')
      ->and($delegated->trace[0]->executionId)->toBe('fake-execution-003-a')
      ->and($delegated->trace[1]->executionId)->toBe('fake-execution-003-b')
      ->and($delegated->trace[2]->executionId)->toBe('fake-execution-003-c')
      ->and($delegated->finalExecutionId)->toBe('fake-execution-003-c')
      ->and($transferred->trace[0]->executionId)->toBe('fake-execution-004-a')
      ->and($transferred->trace[1]->executionId)->toBe('fake-execution-004-b')
      ->and($transferred->finalExecutionId)->toBe('fake-execution-004-b');

    $fake->reset();

    $afterReset = $fake
      ->queueCompletedResult(
          finalAgent: 'support.agent',
          summary: 'Completed support task after reset.',
      )
      ->run(
          new OrchestrationRequest(
              entryAgent: 'support.agent',
              task: 'Handle support request 005',
              input: [],
          ),
      );

    expect($afterReset->orchestrationId)
      ->toBe('fake-orchestration-001')
      ->and($afterReset->finalExecutionId)->toBe('fake-execution-001')
      ->and($fake)->toHaveOrchestrationExecutions(1);
});
