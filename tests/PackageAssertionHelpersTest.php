<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessage;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessageRole;
use CreativeCrafts\LaravelAiAgentKit\Memory\MessageId;
use CreativeCrafts\LaravelAiAgentKit\Testing\Assertions\PackageAssertions;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeProviderPolicy;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeToolRunner;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeVectorStore;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorDocument;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorSearchQuery;

it('provides runtime assertion helpers for fake runtime expectations', function () {
    $fake = new FakeAiRuntime([
      new ExecutionResult(
          runId: 'run-assertions-001',
          output: 'ok',
          provider: 'fake-provider',
          model: 'fake-model',
      ),
    ]);

    $fake->execute(
        new ExecutionRequest(
            runId: 'run-assertions-001',
            prompt: 'Evaluate this response.',
            provider: 'fake-provider',
            model: 'fake-model',
        ),
    );

    PackageAssertions::assertRuntimeExecutedTimes($fake, 1);
    PackageAssertions::assertLastRuntimeRequest($fake, function (ExecutionRequest $request): void {
        expect($request->runId)
          ->toBe('run-assertions-001')
          ->and($request->prompt)->toBe('Evaluate this response.');
    });

    expect($fake)->toHaveRuntimeExecutions(1);
});

it('provides provider assertion helpers for default selection and failover lookups', function () {
    $fake = new FakeProviderPolicy(
        providers: [
        new ProviderDefinition('primary', 'null', true, ['region' => 'eu']),
        new ProviderDefinition('backup', 'null', true, ['region' => 'us']),
      ],
        defaultProviderName: 'primary',
        failoverOrder: ['primary', 'backup'],
    );

    $fake->selectDefault();
    $fake->get('backup');
    $fake->nextAfter('primary');

    PackageAssertions::assertDefaultProviderSelected($fake, 'primary');
    PackageAssertions::assertProviderRequested($fake, 'backup');
    PackageAssertions::assertFailoverLookedUp($fake, 'primary');

    expect($fake)->toHaveSelectedDefaultProvider('primary');
});

it('provides tool assertion helpers for fake tool executions', function () {
    $fake = (new FakeToolRunner())
      ->stub('math.add', static fn (array $input): array => ['sum' => $input['left'] + $input['right']]);

    $result = $fake->execute('math.add', ['left' => 5, 'right' => 7]);

    expect($result)->toBe(['sum' => 12]);

    PackageAssertions::assertToolExecuted($fake, 'math.add', ['left' => 5, 'right' => 7]);

    expect($fake)->toHaveExecutedTool('math.add', ['left' => 5, 'right' => 7]);
});

it('provides memory assertion helpers for stored missing and purged conversations', function () {
    $updatedAt = new DateTimeImmutable('2026-02-01T00:00:00+00:00');
    $conversation = new Conversation(
        id: new ConversationId('conv-assertion-001'),
        createdAt: $updatedAt,
        updatedAt: $updatedAt,
        messages: [
        new ConversationMessage(
            id: new MessageId('msg-assertion-001'),
            role: ConversationMessageRole::User,
            content: 'Hello',
            createdAt: $updatedAt,
        ),
      ],
    );

    $fake = new FakeConversationStore(retentionDays: 30, conversations: [$conversation]);

    PackageAssertions::assertConversationExists($fake, 'conv-assertion-001');
    expect($fake)->toContainConversation('conv-assertion-001');

    $fake->purgeExpired(new DateTimeImmutable('2026-03-15T00:00:00+00:00'));

    PackageAssertions::assertConversationMissing($fake, 'conv-assertion-001');
    PackageAssertions::assertLastPurgeCount($fake, 1);

    expect($fake)
      ->toMissConversation('conv-assertion-001')
      ->toHaveLastPurgeCount(1);
});

it('provides vector assertion helpers for stored documents search recording and deletions', function () {
    $fake = new FakeVectorStore();

    $fake->upsert('support', [
      new VectorDocument(
          id: 'doc-assertion-001',
          embedding: [0.8, 0.2],
          metadata: ['topic' => 'billing'],
      ),
    ]);

    PackageAssertions::assertVectorDocumentStored($fake, 'support', 'doc-assertion-001');

    expect($fake)->toStoreVectorDocument('support', 'doc-assertion-001');

    $fake->search(
        'support',
        new VectorSearchQuery(
            embedding: [1.0, 0.0],
            limit: 1,
            filter: ['topic' => 'billing'],
        ),
    );

    $fake->delete('support', ['doc-assertion-001']);

    PackageAssertions::assertVectorSearchRecordedTimes($fake, 1);
    PackageAssertions::assertVectorDeletionRecorded($fake, 'support', ['doc-assertion-001']);

    expect($fake)
      ->toHaveRecordedVectorDeletion('support', ['doc-assertion-001']);
});

it('provides orchestration assertion helpers for deterministic delegation and ownership transfer expectations', function () {
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

    $delegated = $fake->run(
        new OrchestrationRequest(
            entryAgent: 'support.agent',
            task: 'Handle the support refund workflow',
            input: ['subscription_id' => 'sub-123'],
        ),
    );

    $transferred = $fake->run(
        new OrchestrationRequest(
            entryAgent: 'triage.agent',
            task: 'Escalate ownership to the specialist',
            input: ['case_id' => 'case-123'],
        ),
    );

    PackageAssertions::assertOrchestrationExecutedTimes($fake, 2);
    PackageAssertions::assertLastOrchestrationRequest($fake, function (OrchestrationRequest $request): void {
        expect($request->entryAgent)
          ->toBe('triage.agent')
          ->and($request->task)->toBe('Escalate ownership to the specialist');
    });

    PackageAssertions::assertOrchestrationCompleted($delegated, 'support.agent');
    PackageAssertions::assertDelegationOccurred($delegated, 'support.agent', 'refund.agent');
    PackageAssertions::assertHandoffSummary($delegated, 'support.agent', 'Collect refund context and return the resolution summary.');
    PackageAssertions::assertExecutionTree($delegated, [
      [
        'agent' => 'support.agent',
        'result_kind' => 'delegate',
        'parent_index' => null,
        'target' => 'refund.agent',
        'summary' => 'Collect refund context and return the resolution summary.',
      ],
      [
        'agent' => 'refund.agent',
        'result_kind' => 'complete',
        'parent_index' => 0,
        'summary' => 'Delegated agent [refund.agent] completed the handoff task.',
      ],
      [
        'agent' => 'support.agent',
        'result_kind' => 'complete',
        'parent_index' => 1,
        'summary' => 'Source agent [support.agent] resumed after delegation.',
      ],
    ]);

    PackageAssertions::assertOrchestrationCompleted($transferred, 'specialist.agent');
    PackageAssertions::assertDelegationOccurred($transferred, 'triage.agent', 'specialist.agent');
    PackageAssertions::assertOwnershipTransferred($transferred, 'specialist.agent');

    expect($fake)
      ->toHaveOrchestrationExecutions(2)
      ->and($delegated)
      ->toBeCompletedOrchestration('support.agent')
      ->toHaveDelegatedTo('support.agent', 'refund.agent')
      ->toHaveHandoffSummary('support.agent', 'Collect refund context and return the resolution summary.')
      ->toHaveExecutionTree([
        [
          'agent' => 'support.agent',
          'result_kind' => 'delegate',
          'parent_index' => null,
          'target' => 'refund.agent',
          'summary' => 'Collect refund context and return the resolution summary.',
        ],
        [
          'agent' => 'refund.agent',
          'result_kind' => 'complete',
          'parent_index' => 0,
          'summary' => 'Delegated agent [refund.agent] completed the handoff task.',
        ],
        [
          'agent' => 'support.agent',
          'result_kind' => 'complete',
          'parent_index' => 1,
          'summary' => 'Source agent [support.agent] resumed after delegation.',
        ],
      ])
      ->and($transferred)
      ->toBeCompletedOrchestration('specialist.agent')
      ->toHaveDelegatedTo('triage.agent', 'specialist.agent')
      ->toHaveTransferredControlTo('specialist.agent');
});
