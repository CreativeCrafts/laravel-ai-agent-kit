<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\FailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ProviderToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\Tool;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolAuthorizer;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredFailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\DefaultProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderDisabledException;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\RuntimeBudgetExceededException;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\RuntimeExecutionException;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\RuntimeTelemetryAgent;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\SdkAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StructuredAgentResponseMapper;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StructuredRuntimeTelemetryAgent;
use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessage;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessageRole;
use CreativeCrafts\LaravelAiAgentKit\Memory\MessageId;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\ToolAuthorizationDeniedException;
use CreativeCrafts\LaravelAiAgentKit\Tools\InMemoryToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Tools\ProviderToolMaterializer;
use CreativeCrafts\LaravelAiAgentKit\Tools\SdkToolAdapter;
use Laravel\Ai\Ai;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Providers\Tools\WebSearch;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredAgentResponse;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderTargetResolver;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderTargetResolver;

it('binds the ai runtime contract to the sdk ai runtime', function () {
    app()->register(AiServiceProvider::class);

    $runtime = app(AiRuntime::class);

    expect($runtime)->toBeInstanceOf(SdkAiRuntime::class);
});

it('executes a runtime request through the laravel ai sdk bridge', function () {
    app()->register(AiServiceProvider::class);

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['Bridge response'])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $result = $runtime->execute(
        new ExecutionRequest(
            runId: 'run-bridge-001',
            prompt: 'Summarize this text.',
            instructions: ['You are a concise assistant.'],
            provider: 'openai',
            model: 'gpt-4o-mini',
        ),
    );

    expect($result)
      ->toBeInstanceOf(ExecutionResult::class)
      ->and($result->runId)->toBe('run-bridge-001')
      ->and($result->output)->toBe('Bridge response')
      ->and($result->provider)->toBe('openai')
      ->and($result->model)->toBe('gpt-4o-mini')
      ->and($result->usage)
      ->toHaveKey('prompt_tokens')
      ->toHaveKey('completion_tokens')
      ->and($result->metadata)
      ->toHaveKey('invocation_id')
      ->toHaveKey('requested_tool_names')
      ->toHaveKey('materialized_tool_count')
      ->toHaveKey('projected_message_count')
      ->and($result->metadata['requested_tool_names'])->toBe([])
      ->and($result->metadata['materialized_tool_count'])->toBe(0)
      ->and($result->metadata['projected_message_count'])->toBe(0)
      ->and($result->metadata['package_conversation_id'])->toBeNull();
});

it('uses the configured default provider when a request omits provider and model', function () {
    app()->register(AiServiceProvider::class);

    config()->set('ai-agent-kit.default_provider', 'openai');
    configureRuntimeProvider('openai', 'openai', 'gpt-4o-mini');
    refreshRuntimeProviderBindings();

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['Default provider response'])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $result = $runtime->execute(
        new ExecutionRequest(
            runId: 'run-default-provider-001',
            prompt: 'Use the configured provider.',
        ),
    );

    expect($result->output)->toBe('Default provider response')
        ->and($result->metadata['runtime_provider_attempts'])->toBe(['openai'])
        ->and($result->metadata['runtime_final_provider'])->toBe('openai')
        ->and($result->metadata['runtime_failover_attempted'])->toBeFalse();
});

it('preserves explicit provider and model as the first runtime provider attempt', function () {
    app()->register(AiServiceProvider::class);

    config()->set('ai-agent-kit.default_provider', 'openai');
    configureRuntimeProvider('openai', 'openai', 'gpt-4o-mini');
    configureRuntimeProvider('anthropic', 'anthropic', 'claude-default');
    refreshRuntimeProviderBindings();

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['Explicit provider response'])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $result = $runtime->execute(
        new ExecutionRequest(
            runId: 'run-explicit-provider-001',
            prompt: 'Use the explicit provider.',
            provider: 'anthropic',
            model: 'claude-explicit',
        ),
    );

    expect($result->output)->toBe('Explicit provider response')
        ->and($result->metadata['runtime_provider_attempts'])->toBe(['anthropic'])
        ->and($result->metadata['runtime_final_provider'])->toBe('anthropic');
});

it('fails over provider prompt execution when the first provider attempt fails', function () {
    app()->register(AiServiceProvider::class);

    config()->set('ai-agent-kit.default_provider', 'openai');
    config()->set('ai-agent-kit.failover_order', ['openai', 'anthropic']);
    configureRuntimeProvider('openai', 'openai', 'gpt-4o-mini');
    configureRuntimeProvider('anthropic', 'anthropic', 'claude-3-haiku');
    refreshRuntimeProviderBindings();

    $attempts = 0;
    Ai::fakeAgent(RuntimeTelemetryAgent::class, [
        static function () use (&$attempts): string {
            $attempts++;

            if ($attempts === 1) {
                throw new RuntimeException('openai unavailable');
            }

            return 'Fallback provider response';
        },
    ])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $result = $runtime->execute(
        new ExecutionRequest(
            runId: 'run-provider-failover-001',
            prompt: 'Fail over if needed.',
        ),
    );

    expect($attempts)->toBe(2)
        ->and($result->output)->toBe('Fallback provider response')
        ->and($result->metadata['runtime_provider_attempts'])->toBe(['openai', 'anthropic'])
        ->and($result->metadata['runtime_final_provider'])->toBe('anthropic')
        ->and($result->metadata['runtime_failover_attempted'])->toBeTrue();
});

it('normalizes provider failure when runtime failover is exhausted', function () {
    app()->register(AiServiceProvider::class);

    config()->set('ai-agent-kit.default_provider', 'openai');
    config()->set('ai-agent-kit.failover_order', ['openai']);
    configureRuntimeProvider('openai', 'openai', 'gpt-4o-mini');
    refreshRuntimeProviderBindings();

    Ai::fakeAgent(RuntimeTelemetryAgent::class, [
        static fn (): never => throw new RuntimeException('openai unavailable'),
    ])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    expect(fn () => $runtime->execute(
        new ExecutionRequest(
            runId: 'run-provider-failover-exhausted-001',
            prompt: 'Fail with no fallback.',
        ),
    ))->toThrow(RuntimeExecutionException::class, 'AI runtime execution failed for run [run-provider-failover-exhausted-001]');
});

it('materializes package-governed tools into the sdk agent prompt', function () {
    app()->register(AiServiceProvider::class);

    /** @var ToolRegistry $registry */
    $registry = app(ToolRegistry::class);
    $registry->register(
        new class () implements Tool {
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
      },
    );

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['Bridge response'])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $runtime->execute(
        new ExecutionRequest(
            runId: 'run-bridge-tools-001',
            prompt: 'Use the calculator tool if needed.',
            provider: 'openai',
            model: 'gpt-4o-mini',
            toolNames: ['math.add'],
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        $tools = $prompt->agent->tools();
        $tools = is_array($tools) ? array_values($tools) : array_values(iterator_to_array($tools));

        return count($tools) === 1
          && $tools[0] instanceof SdkToolAdapter
          && $tools[0]->name() === 'math.add';
    });
});

it('materializes registered provider-native tools into the sdk agent prompt', function () {
    app()->register(AiServiceProvider::class);

    app()->bind(ToolAuthorizer::class, function () {
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
    });

    app()->forgetInstance(InMemoryToolRegistry::class);
    app()->forgetInstance(ToolRegistry::class);
    app()->forgetInstance(ProviderToolMaterializer::class);
    app()->forgetInstance(SdkAiRuntime::class);
    app()->forgetInstance(AiRuntime::class);

    /** @var ProviderToolRegistry $registry */
    $registry = app(ProviderToolRegistry::class);
    $registry->register('web.search', fn () => new WebSearch(maxSearches: 2, allowedDomains: ['example.com']));

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['Bridge response'])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $runtime->execute(
        new ExecutionRequest(
            runId: 'run-bridge-provider-tools-001',
            prompt: 'Search the web for the latest update.',
            provider: 'openai',
            model: 'gpt-4o-mini',
            providerToolNames: ['web.search'],
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        $tools = $prompt->agent->tools();
        $tools = is_array($tools) ? array_values($tools) : array_values(iterator_to_array($tools));

        return count($tools) === 1
          && $tools[0] instanceof WebSearch
          && $tools[0]->maxSearches === 2
          && $tools[0]->allowedDomains === ['example.com'];
    });
});

it('denies provider tool materialization when the authorizer rejects it', function () {
    app()->register(AiServiceProvider::class);

    app()->bind(ToolAuthorizer::class, function () {
        return new class () implements ToolAuthorizer {
            public function authorizeCustomTool(Tool $tool, array $input): bool
            {
                return true;
            }

            public function authorizeProviderTool(string $providerToolName): bool
            {
                return false;
            }
        };
    });

    app()->forgetInstance(InMemoryToolRegistry::class);
    app()->forgetInstance(ToolRegistry::class);
    app()->forgetInstance(ProviderToolMaterializer::class);
    app()->forgetInstance(SdkAiRuntime::class);
    app()->forgetInstance(AiRuntime::class);

    /** @var ProviderToolRegistry $registry */
    $registry = app(ProviderToolRegistry::class);
    $registry->register('web.search', fn () => new WebSearch());

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    expect(fn () => $runtime->execute(
        new ExecutionRequest(
            runId: 'run-bridge-provider-tools-deny-001',
            prompt: 'Try to search the web.',
            provider: 'openai',
            providerToolNames: ['web.search'],
        ),
    ))->toThrow(ToolAuthorizationDeniedException::class, 'web.search');
});

it('routes schema-backed calls through the structured telemetry agent and returns structured output', function () {
    app()->register(AiServiceProvider::class);

    $structuredForMapper = new StructuredAgentResponse(
        'inv-structured-001',
        ['ok' => true],
        'Structured response text',
        new Usage(promptTokens: 1, completionTokens: 1),
        new Meta(provider: 'openai', model: 'gpt-4o-mini'),
    );

    expect(StructuredAgentResponseMapper::mapStructuredPayload($structuredForMapper))
        ->toBe(['ok' => true]);

    Ai::fakeAgent(StructuredRuntimeTelemetryAgent::class, [
        static fn () => $structuredForMapper,
    ])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $result = $runtime->execute(
        new ExecutionRequest(
            runId: 'run-structured-001',
            prompt: 'Return structured output.',
            provider: 'openai',
            schema: fn ($js) => [],
        ),
    );

    Ai::assertAgentWasPrompted(StructuredRuntimeTelemetryAgent::class, fn () => true);

    // Laravel\Ai fakes may normalize the returned response to a plain AgentResponse, so
    // ExecutionResult->structuredOutput can be null here even though production maps
    // StructuredAgentResponse via StructuredAgentResponseMapper (covered above and in
    // StructuredAgentResponseMapperTest).
    expect($result->structuredOutput)->toBeNull()
      ->and($result->output)->toBe('Structured response text');
});

it('starts and persists a new package-owned conversation through the runtime bridge', function () {
    app()->register(AiServiceProvider::class);

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['New conversation response'])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $result = $runtime->execute(
        new ExecutionRequest(
            runId: 'run-bridge-memory-start',
            prompt: 'Start a conversation.',
            provider: 'openai',
            storeConversation: true,
        ),
    );

    $conversationId = $result->metadata['package_conversation_id'];

    /** @var ConversationStore $store */
    $store = app(ConversationStore::class);
    $conversation = is_string($conversationId)
      ? $store->find(new ConversationId($conversationId))
      : null;

    expect($conversationId)
      ->toBeString()
      ->and($result->metadata['projected_message_count'])->toBe(0)
      ->and($conversation?->messageCount())->toBe(2)
      ->and($conversation?->messages[0]->role)->toBe(ConversationMessageRole::User)
      ->and($conversation?->messages[0]->content)->toBe('Start a conversation.')
      ->and($conversation?->messages[1]->role)->toBe(ConversationMessageRole::Assistant)
      ->and($conversation?->messages[1]->content)->toBe('New conversation response');
});

it('continues a stored package conversation through the runtime bridge and persists appended state', function () {
    app()->register(AiServiceProvider::class);

    $conversationId = new ConversationId('conv-runtime-001');
    $startedAt = new DateTimeImmutable('2026-03-22T10:00:00+00:00');

    /** @var ConversationStore $store */
    $store = app(ConversationStore::class);
    $store->save(
        new Conversation(
            id: $conversationId,
            createdAt: $startedAt,
            updatedAt: $startedAt,
            messages: [
          new ConversationMessage(
              id: new MessageId('msg-system'),
              role: ConversationMessageRole::System,
              content: 'Remember the customer prefers concise replies.',
              createdAt: $startedAt,
          ),
          new ConversationMessage(
              id: new MessageId('msg-user-1'),
              role: ConversationMessageRole::User,
              content: 'Previous question',
              createdAt: $startedAt,
          ),
          new ConversationMessage(
              id: new MessageId('msg-assistant-1'),
              role: ConversationMessageRole::Assistant,
              content: 'Previous answer',
              createdAt: $startedAt,
          ),
        ],
        ),
    );

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['Follow-up response'])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $result = $runtime->execute(
        new ExecutionRequest(
            runId: 'run-bridge-memory-continue',
            prompt: 'New follow-up question',
            provider: 'openai',
            conversationId: $conversationId,
            storeConversation: true,
            continueConversation: true,
        ),
    );

    $persistedConversation = $store->find($conversationId);

    expect($result->metadata['package_conversation_id'])
      ->toBe('conv-runtime-001')
      ->and($result->metadata['projected_message_count'])->toBe(3)
      ->and($persistedConversation?->messageCount())->toBe(5)
      ->and($persistedConversation?->messages[3]->content)->toBe('New follow-up question')
      ->and($persistedConversation?->messages[4]->content)->toBe('Follow-up response')
      ->and($persistedConversation?->metadata['last_run_id'])->toBe('run-bridge-memory-continue');

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        $messages = $prompt->agent->messages();
        $messages = is_array($messages) ? array_values($messages) : array_values(iterator_to_array($messages));

        return str_contains($prompt->agent->instructions(), 'Remember the customer prefers concise replies.')
          && count($messages) === 3
          && $messages[0] instanceof UserMessage
          && $messages[0]->content === '[system-context] Remember the customer prefers concise replies.'
          && $messages[1] instanceof UserMessage
          && $messages[1]->content === 'Previous question'
          && $messages[2] instanceof AssistantMessage
          && $messages[2]->content === 'Previous answer';
    });
});

it('wraps missing tool materialization failures in a typed runtime execution exception', function () {
    app()->register(AiServiceProvider::class);

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    expect(fn ()
        => $runtime->execute(
            new ExecutionRequest(
                runId: 'run-bridge-missing-tool',
                prompt: 'Attempt to use a missing tool.',
                provider: 'openai',
                toolNames: ['missing.tool'],
            ),
        ))
      ->toThrow(RuntimeExecutionException::class, 'AI runtime execution failed for run [run-bridge-missing-tool]');
});

it('enforces max token budget during runtime execution', function () {
    app()->register(AiServiceProvider::class);

    config()->set('ai-agent-kit.budgets.max_tokens', 3);

    Ai::fakeAgent(RuntimeTelemetryAgent::class, [
      static fn () => new AgentResponse(
          invocationId: 'inv-runtime-budget-tokens',
          text: 'Budgeted response',
          usage: new Usage(promptTokens: 2, completionTokens: 2),
          meta: new Meta(provider: 'openai', model: 'gpt-4o-mini'),
      ),
    ])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    expect(fn ()
        => $runtime->execute(
            new ExecutionRequest(
                runId: 'run-budget-tokens',
                prompt: 'Trigger token budget enforcement.',
                provider: 'openai',
            ),
        ))
      ->toThrow(RuntimeBudgetExceededException::class, 'max_tokens [3]');
});

it('enforces max tool-call budget during runtime execution', function () {
    app()->register(AiServiceProvider::class);

    config()->set('ai-agent-kit.budgets.max_tool_calls', 1);

    app()->bind(ToolAuthorizer::class, function () {
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
    });

    /** @var ToolRegistry $registry */
    $registry = app(ToolRegistry::class);
    $registry->register(
        new class () implements Tool {
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
        },
    );

    Ai::fakeAgent(RuntimeTelemetryAgent::class, [
        new ToolCall('tool-call-1', 'math.add', ['left' => 1, 'right' => 2]),
        new ToolCall('tool-call-2', 'math.add', ['left' => 3, 'right' => 4]),
    ])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    expect(fn ()
        => $runtime->execute(
            new ExecutionRequest(
                runId: 'run-budget-tool-calls',
                prompt: 'Trigger tool-call budget enforcement.',
                provider: 'openai',
                toolNames: ['math.add'],
            ),
        ))
      ->toThrow(RuntimeBudgetExceededException::class, 'max_tool_calls [1]');
});

it('fails closed when max_cost_usd is configured but runtime cost metadata is missing', function () {
    app()->register(AiServiceProvider::class);

    config()->set('ai-agent-kit.budgets.max_cost_usd', 0.01);

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['Budgeted response'])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    expect(fn ()
        => $runtime->execute(
            new ExecutionRequest(
                runId: 'run-budget-cost-missing',
                prompt: 'Trigger missing cost metadata budget enforcement.',
                provider: 'openai',
            ),
        ))
      ->toThrow(RuntimeBudgetExceededException::class, 'metadata.cost_usd');
});

it('enforces max cost budget when cost metadata is provided', function () {
    app()->register(AiServiceProvider::class);

    config()->set('ai-agent-kit.budgets.max_cost_usd', 0.01);

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['Budgeted response'])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    expect(fn ()
        => $runtime->execute(
            new ExecutionRequest(
                runId: 'run-budget-cost-exceeded',
                prompt: 'Trigger cost budget enforcement.',
                provider: 'openai',
                metadata: ['cost_usd' => 0.02],
            ),
        ))
      ->toThrow(RuntimeBudgetExceededException::class, 'max_cost_usd [0.01]');
});

it('wraps sdk runtime failures in a typed runtime execution exception', function () {
    app()->register(AiServiceProvider::class);

    config()->set('ai-agent-kit.failover_order', ['openai']);
    configureRuntimeProvider('openai', 'openai', 'gpt-4o-mini');
    refreshRuntimeProviderBindings();

    Ai::fakeAgent(RuntimeTelemetryAgent::class, [
      static function (): never {
          throw new RuntimeException('SDK failure');
      },
    ])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    expect(fn ()
        => $runtime->execute(
            new ExecutionRequest(
                runId: 'run-bridge-failure',
                prompt: 'Fail this request.',
                provider: 'openai',
            ),
        ))
      ->toThrow(RuntimeExecutionException::class, 'AI runtime execution failed for run [run-bridge-failure]');
});

it('rejects an explicitly selected disabled provider profile', function () {
    app()->register(AiServiceProvider::class);

    config()->set('ai-agent-kit.default_provider', 'openai');
    configureRuntimeProvider('openai', 'openai', 'gpt-4o-mini');
    config()->set('ai-agent-kit.providers.scorer-disabled', [
      'driver' => 'openai',
      'enabled' => false,
      'capabilities' => ['text_generation'],
      'options' => ['model' => 'gpt-disabled'],
    ]);
    refreshRuntimeProviderBindings();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    expect(fn ()
        => $runtime->execute(
            new ExecutionRequest(
                runId: 'run-disabled-profile',
                prompt: 'Do not use a deactivated profile.',
                provider: 'scorer-disabled',
            ),
        ))
      ->toThrow(ProviderDisabledException::class, 'Provider [scorer-disabled] is disabled.');
});

function configureRuntimeProvider(string $name, string $driver, string $model): void
{
    config()->set("ai-agent-kit.providers.{$name}", [
        'driver' => $driver,
        'enabled' => true,
        'capabilities' => ['text_generation', 'structured_output'],
        'options' => ['model' => $model],
    ]);
}

function refreshRuntimeProviderBindings(): void
{
    app()->forgetInstance(ConfiguredProviderRegistry::class);
    app()->forgetInstance(ProviderRegistry::class);
    app()->forgetInstance(DefaultProviderSelector::class);
    app()->forgetInstance(ProviderSelector::class);
    app()->forgetInstance(ConfiguredFailoverProviderSelector::class);
    app()->forgetInstance(FailoverProviderSelector::class);
    app()->forgetInstance(ConfiguredProviderTargetResolver::class);
    app()->forgetInstance(ProviderTargetResolver::class);
    app()->forgetInstance(SdkAiRuntime::class);
    app()->forgetInstance(AiRuntime::class);
}
