<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessage;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessageRole;
use CreativeCrafts\LaravelAiAgentKit\Memory\MessageId;
use CreativeCrafts\LaravelAiAgentKit\Testing\Assertions\PackageAssertions;
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
