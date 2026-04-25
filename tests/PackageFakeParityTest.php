<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\Tool;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolAuthorizer;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredFailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\DefaultProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderDisabledException;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderNotInFailoverOrderException;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessage;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessageRole;
use CreativeCrafts\LaravelAiAgentKit\Memory\InMemoryConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\MessageId;
use CreativeCrafts\LaravelAiAgentKit\Testing\Assertions\PackageAssertions;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeProviderPolicy;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeToolRunner;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeVectorStore;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\InvalidToolInputException;
use CreativeCrafts\LaravelAiAgentKit\Tools\InMemoryToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Vector\InMemoryVectorStore;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorDocument;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorSearchQuery;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorSearchResult;
use Illuminate\Config\Repository;

it('keeps fake ai runtime aligned with package execution result semantics', function (): void {
    $runtime = (new FakeAiRuntime())
      ->queueCallback(static function (ExecutionRequest $request): ExecutionResult {
          return new ExecutionResult(
              runId: $request->runId,
              output: strtoupper($request->prompt),
              provider: $request->provider,
              model: $request->model,
              usage: [
              'prompt_tokens' => 4,
              'completion_tokens' => 2,
              'total_tokens' => 6,
            ],
              metadata: [
              'source' => 'callback',
            ],
          );
      });

    $request = new ExecutionRequest(
        runId: 'run-runtime-parity-001',
        prompt: 'summarize this',
        provider: 'fake-provider',
        model: 'fake-model',
    );

    $result = $runtime->execute($request);

    expect($result->runId)
      ->toBe('run-runtime-parity-001')
      ->and($result->output)->toBe('SUMMARIZE THIS')
      ->and($result->provider)->toBe('fake-provider')
      ->and($result->model)->toBe('fake-model')
      ->and($result->usage)->toBe([
        'prompt_tokens' => 4,
        'completion_tokens' => 2,
        'total_tokens' => 6,
      ])
      ->and($result->metadata)->toBe([
        'source' => 'callback',
      ]);

    PackageAssertions::assertRuntimeExecutedTimes($runtime, 1);
    PackageAssertions::assertLastRuntimeRequest($runtime, function (ExecutionRequest $lastRequest): void {
        expect($lastRequest->runId)
          ->toBe('run-runtime-parity-001')
          ->and($lastRequest->prompt)->toBe('summarize this');
    });

    $defaultResult = (new FakeAiRuntime())->execute(
        new ExecutionRequest(
            runId: 'run-runtime-parity-002',
            prompt: 'fallback',
            provider: 'default-provider',
            model: 'default-model',
        ),
    );

    expect($defaultResult->runId)
      ->toBe('run-runtime-parity-002')
      ->and($defaultResult->output)->toBe('')
      ->and($defaultResult->provider)->toBe('default-provider')
      ->and($defaultResult->model)->toBe('default-model')
      ->and($defaultResult->metadata['fake_runtime'] ?? false)->toBeTrue();
});

it('keeps fake provider policy aligned with configured provider selection and failover semantics', function (): void {
    $config = new Repository([
      'ai-agent-kit' => [
        'providers' => [
          'backup' => ['driver' => 'null', 'enabled' => true, 'options' => ['region' => 'us']],
          'primary' => ['driver' => 'null', 'enabled' => true, 'options' => ['region' => 'eu']],
          'tertiary' => ['driver' => 'null', 'enabled' => true, 'options' => ['region' => 'apac']],
        ],
        'default_provider' => 'primary',
        'failover_order' => ['backup', 'primary', 'tertiary'],
      ],
    ]);

    $realRegistry = new ConfiguredProviderRegistry($config);
    $realSelector = new DefaultProviderSelector($config, $realRegistry);
    $realFailover = new ConfiguredFailoverProviderSelector($config, $realRegistry);

    $fake = new FakeProviderPolicy(
        providers: [
        new ProviderDefinition('backup', 'null', true, ['region' => 'us']),
        new ProviderDefinition('primary', 'null', true, ['region' => 'eu']),
        new ProviderDefinition('tertiary', 'null', true, ['region' => 'apac']),
      ],
        defaultProviderName: 'primary',
        failoverOrder: ['backup', 'primary', 'tertiary'],
    );

    expect($fake->selectDefault()->name)
      ->toBe($realSelector->selectDefault()->name)
      ->and(packageFakeParityProviderNames($fake->ordered()))
      ->toBe(packageFakeParityProviderNames($realFailover->ordered()))
      ->and($fake->nextAfter('primary')?->name)
      ->toBe($realFailover->nextAfter('primary')?->name);
});

it('keeps fake provider policy aligned with disabled-provider failover behavior', function (): void {
    $config = new Repository([
      'ai-agent-kit' => [
        'providers' => [
          'primary' => ['driver' => 'null', 'enabled' => true, 'options' => []],
          'disabled' => ['driver' => 'null', 'enabled' => false, 'options' => []],
        ],
        'default_provider' => 'primary',
        'failover_order' => ['primary', 'disabled'],
      ],
    ]);

    $realRegistry = new ConfiguredProviderRegistry($config);
    $realFailover = new ConfiguredFailoverProviderSelector($config, $realRegistry);

    $fake = new FakeProviderPolicy(
        providers: [
        new ProviderDefinition('primary', 'null', true, []),
        new ProviderDefinition('disabled', 'null', false, []),
      ],
        defaultProviderName: 'primary',
        failoverOrder: ['primary', 'disabled'],
    );

    expect(fn (): array => $fake->ordered())
      ->toThrow(ProviderDisabledException::class)
      ->and(fn (): array => $realFailover->ordered())->toThrow(ProviderDisabledException::class);
});

it('keeps fake provider policy aligned with not-in-failover-order behavior when the order is otherwise valid', function (): void {
    $config = new Repository([
      'ai-agent-kit' => [
        'providers' => [
          'primary' => ['driver' => 'null', 'enabled' => true, 'options' => []],
          'backup' => ['driver' => 'null', 'enabled' => true, 'options' => []],
          'out_of_band' => ['driver' => 'null', 'enabled' => true, 'options' => []],
        ],
        'default_provider' => 'primary',
        'failover_order' => ['primary', 'backup'],
      ],
    ]);

    $realRegistry = new ConfiguredProviderRegistry($config);
    $realFailover = new ConfiguredFailoverProviderSelector($config, $realRegistry);

    $fake = new FakeProviderPolicy(
        providers: [
        new ProviderDefinition('primary', 'null', true, []),
        new ProviderDefinition('backup', 'null', true, []),
        new ProviderDefinition('out_of_band', 'null', true, []),
      ],
        defaultProviderName: 'primary',
        failoverOrder: ['primary', 'backup'],
    );

    expect(fn (): ?ProviderDefinition => $fake->nextAfter('out_of_band'))
      ->toThrow(ProviderNotInFailoverOrderException::class)
      ->and(fn (): ?ProviderDefinition => $realFailover->nextAfter('out_of_band'))->toThrow(ProviderNotInFailoverOrderException::class);
});

it('keeps fake tool runner aligned with the in-memory tool registry for valid and invalid inputs', function (): void {
    $tool = packageFakeParityMathTool();

    $fake = new FakeToolRunner([$tool]);
    $real = new InMemoryToolRegistry(
        authorizer: packageFakeParityAllowAllToolAuthorizer(),
        tools: [$tool],
    );

    $validInput = ['left' => 4, 'right' => 6];

    expect($fake->execute('math.add', $validInput))
      ->toBe($real->execute('math.add', $validInput));

    PackageAssertions::assertToolExecuted($fake, 'math.add', $validInput);

    $invalidInput = ['left' => 4, 'right' => 6, 'extra' => 9];

    expect(fn (): array => $fake->execute('math.add', $invalidInput))
      ->toThrow(InvalidToolInputException::class, 'unexpected property [extra]')
      ->and(fn (): array => $real->execute('math.add', $invalidInput))
      ->toThrow(InvalidToolInputException::class, 'unexpected property [extra]');
});

it('keeps fake conversation store aligned with the in-memory conversation store contract semantics', function (): void {
    $fake = new FakeConversationStore(retentionDays: 30);
    $real = new InMemoryConversationStore(retentionDays: 30);

    $persistedConversation = packageFakeParityConversation(
        conversationId: 'conv-parity-persisted',
        messageId: 'msg-parity-persisted',
        content: 'Persist this conversation.',
        timestamp: '2026-02-14T09:00:00+00:00',
        messageMetadata: ['channel' => 'cli'],
        conversationMetadata: ['scope' => 'parity'],
    );

    $fake->save($persistedConversation);
    $real->save($persistedConversation);

    expect(packageFakeParityConversationSnapshot($fake->find(new ConversationId('conv-parity-persisted'))))
      ->toBe(packageFakeParityConversationSnapshot($real->find(new ConversationId('conv-parity-persisted'))));

    $fake->delete(new ConversationId('conv-parity-persisted'));
    $real->delete(new ConversationId('conv-parity-persisted'));

    expect(packageFakeParityConversationSnapshot($fake->find(new ConversationId('conv-parity-persisted'))))
      ->toBe(packageFakeParityConversationSnapshot($real->find(new ConversationId('conv-parity-persisted'))));

    $expiredConversation = packageFakeParityConversation(
        conversationId: 'conv-parity-expired',
        messageId: 'msg-parity-expired',
        content: 'Expired content.',
        timestamp: '2026-01-01T08:00:00+00:00',
        messageMetadata: ['channel' => 'voice'],
        conversationMetadata: ['scope' => 'expired'],
    );

    $activeConversation = packageFakeParityConversation(
        conversationId: 'conv-parity-active',
        messageId: 'msg-parity-active',
        content: 'Active content.',
        timestamp: '2026-03-14T08:00:00+00:00',
        messageMetadata: ['channel' => 'voice'],
        conversationMetadata: ['scope' => 'active'],
    );

    $fake->save($expiredConversation);
    $real->save($expiredConversation);
    $fake->save($activeConversation);
    $real->save($activeConversation);

    $threshold = new DateTimeImmutable('2026-03-01T00:00:00+00:00');

    expect($fake->purgeExpired($threshold))
      ->toBe($real->purgeExpired($threshold))
      ->and(packageFakeParityConversationSnapshot($fake->find(new ConversationId('conv-parity-expired'))))
      ->toBe(packageFakeParityConversationSnapshot($real->find(new ConversationId('conv-parity-expired'))))
      ->and(packageFakeParityConversationSnapshot($fake->find(new ConversationId('conv-parity-active'))))
      ->toBe(packageFakeParityConversationSnapshot($real->find(new ConversationId('conv-parity-active'))));
});

it('keeps fake vector store aligned with the in-memory vector store search and delete semantics', function (): void {
    $fake = new FakeVectorStore();
    $real = new InMemoryVectorStore();

    $documents = [
      new VectorDocument(
          id: 'doc-parity-001',
          embedding: [0.9, 0.1],
          metadata: ['topic' => 'billing'],
      ),
      new VectorDocument(
          id: 'doc-parity-002',
          embedding: [0.3, 0.7],
          metadata: ['topic' => 'shipping'],
      ),
      new VectorDocument(
          id: 'doc-parity-003',
          embedding: [0.8, 0.2],
          metadata: ['topic' => 'billing'],
      ),
    ];

    $query = new VectorSearchQuery(
        embedding: [1.0, 0.0],
        limit: 2,
        filter: ['topic' => 'billing'],
    );

    $fake->upsert('support', $documents);
    $real->upsert('support', $documents);

    expect(packageFakeParityVectorSearchResults($fake->search('support', $query)))
      ->toBe(packageFakeParityVectorSearchResults($real->search('support', $query)))
      ->and($fake->delete('support', ['doc-parity-001']))
      ->toBe($real->delete('support', ['doc-parity-001']))
      ->and(packageFakeParityVectorSearchResults($fake->search('support', $query)))
      ->toBe(packageFakeParityVectorSearchResults($real->search('support', $query)));
});

it('keeps fake agent orchestrator aligned with package orchestration result semantics', function (): void {
    $request = new OrchestrationRequest(
        entryAgent: 'support.agent',
        task: 'Handle a support workflow',
        input: ['case_id' => 'case-001'],
    );

    $result = (new FakeAgentOrchestrator())->run($request);

    PackageAssertions::assertOrchestrationCompleted($result, 'support.agent');

    expect($result)
      ->toBeInstanceOf(OrchestrationResult::class)
      ->toBeCompletedOrchestration('support.agent')
      ->and($result->finalExecutionId)->toBe($result->trace[0]->executionId)
      ->and($result->finalOutput)->toBe([
        'fake_orchestrator' => true,
        'task' => 'Handle a support workflow',
      ])
      ->and($result->trace)->toHaveCount(1)
      ->and($result->trace[0]->parentExecutionId)->toBeNull()
      ->and($result->trace[0]->agentKey)->toBe('support.agent')
      ->and($result->trace[0]->resultKind)->toBe('complete');
});

function packageFakeParityAllowAllToolAuthorizer(): ToolAuthorizer
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

function packageFakeParityMathTool(): Tool
{
    return new class () implements Tool {
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
            return [
              'sum' => $input['left'] + $input['right'],
            ];
        }
    };
}

/**
 * @param list<ProviderDefinition> $providers
 * @return list<string>
 */
function packageFakeParityProviderNames(array $providers): array
{
    return array_map(
        static fn (ProviderDefinition $provider): string => $provider->name,
        $providers,
    );
}

function packageFakeParityConversation(
    string $conversationId,
    string $messageId,
    string $content,
    string $timestamp,
    array $messageMetadata = [],
    array $conversationMetadata = [],
): Conversation {
    $dateTime = new DateTimeImmutable($timestamp);

    return new Conversation(
        id: new ConversationId($conversationId),
        createdAt: $dateTime,
        updatedAt: $dateTime,
        messages: [
        new ConversationMessage(
            id: new MessageId($messageId),
            role: ConversationMessageRole::User,
            content: $content,
            createdAt: $dateTime,
            metadata: $messageMetadata,
        ),
      ],
        metadata: $conversationMetadata,
    );
}

/**
 * @return array{id:string, message_count:int, latest_message:string|null, latest_role:string|null, latest_channel:mixed, scope:mixed}|null
 */
function packageFakeParityConversationSnapshot(?Conversation $conversation): ?array
{
    if ($conversation === null) {
        return null;
    }

    return [
      'id' => $conversation->id->toString(),
      'message_count' => $conversation->messageCount(),
      'latest_message' => $conversation->latestMessage()?->content,
      'latest_role' => $conversation->latestMessage()?->role->value,
      'latest_channel' => $conversation->latestMessage()?->metadataValue('channel'),
      'scope' => $conversation->metadataValue('scope'),
    ];
}

/**
 * @param list<VectorSearchResult> $results
 * @return list<array{id:string, score:float, metadata:array<string, mixed>}>
 */
function packageFakeParityVectorSearchResults(array $results): array
{
    return array_map(
        static fn (VectorSearchResult $result): array
          => [
        'id' => $result->id,
        'score' => $result->score,
        'metadata' => $result->metadata,
      ],
        $results,
    );
}
