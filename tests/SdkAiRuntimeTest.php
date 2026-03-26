<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\Tool;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\RuntimeExecutionException;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\RuntimeTelemetryAgent;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\SdkAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessage;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessageRole;
use CreativeCrafts\LaravelAiAgentKit\Memory\MessageId;
use CreativeCrafts\LaravelAiAgentKit\Tools\SdkToolAdapter;
use CreativeCrafts\LaravelAiAgentKit\Tools\SdkToolMaterializer;
use Laravel\Ai\Ai;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Providers\Tools\WebSearch;

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

it('materializes explicitly configured provider-native tools into the sdk agent prompt', function () {
    app()->register(AiServiceProvider::class);

    config()->set('ai-agent-kit.tools.provider_tools', [
      'web.search' => [
        'type' => 'web_search',
        'enabled' => true,
        'max_searches' => 2,
        'allowed_domains' => ['example.com'],
      ],
    ]);

    app()->forgetInstance(SdkToolMaterializer::class);
    app()->forgetInstance(SdkAiRuntime::class);
    app()->forgetInstance(AiRuntime::class);

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['Bridge response'])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $runtime->execute(
        new ExecutionRequest(
            runId: 'run-bridge-provider-tools-001',
            prompt: 'Search the web for the latest update.',
            provider: 'openai',
            model: 'gpt-4o-mini',
            toolNames: ['web.search'],
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

it('wraps sdk runtime failures in a typed runtime execution exception', function () {
    app()->register(AiServiceProvider::class);

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
