<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationContextManager;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\RuntimeConversationMemoryBridge;
use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessage;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessageRole;
use CreativeCrafts\LaravelAiAgentKit\Memory\MessageId;
use Illuminate\Support\Collection;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;

it('projects package-owned conversation memory into runtime context', function () {
    $conversationId = new ConversationId('conv-project-001');
    $startedAt = new DateTimeImmutable('2026-03-22T09:00:00+00:00');

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
              content: 'System memory',
              createdAt: $startedAt,
          ),
          new ConversationMessage(
              id: new MessageId('msg-user'),
              role: ConversationMessageRole::User,
              content: 'Historic user message',
              createdAt: $startedAt,
          ),
          new ConversationMessage(
              id: new MessageId('msg-assistant'),
              role: ConversationMessageRole::Assistant,
              content: 'Historic assistant message',
              createdAt: $startedAt,
          ),
          new ConversationMessage(
              id: new MessageId('msg-tool'),
              role: ConversationMessageRole::Tool,
              content: 'Historic tool message',
              createdAt: $startedAt,
          ),
        ],
        ),
    );

    $bridge = new RuntimeConversationMemoryBridge(
        app(ConversationContextManager::class),
    );

    $projected = $bridge->project(
        new ExecutionRequest(
            runId: 'run-project-001',
            prompt: 'Next prompt',
            conversationId: $conversationId,
            storeConversation: true,
            continueConversation: true,
        ),
    );

    expect($projected->projectedMessageCount())
      ->toBe(4)
      ->and($projected->systemInstructions)->toBe(['System memory'])
      ->and($projected->messages[0])->toBeInstanceOf(UserMessage::class)
      ->and($projected->messages[0]->content)->toBe('[system-context] System memory')
      ->and($projected->messages[1])->toBeInstanceOf(UserMessage::class)
      ->and($projected->messages[1]->content)->toBe('Historic user message')
      ->and($projected->messages[2])->toBeInstanceOf(AssistantMessage::class)
      ->and($projected->messages[2]->content)->toBe('Historic assistant message')
      ->and($projected->messages[3])->toBeInstanceOf(ToolResultMessage::class);
});

it('reconciles runtime output back into package-owned memory deterministically', function () {
    $bridge = new RuntimeConversationMemoryBridge(
        app(ConversationContextManager::class),
    );

    $request = new ExecutionRequest(
        runId: 'run-reconcile-001',
        prompt: 'Current prompt',
        provider: 'openai',
        model: 'gpt-4o-mini',
        conversationId: new ConversationId('conv-reconcile-001'),
        storeConversation: true,
    );

    $projected = $bridge->project($request);

    $response = new AgentResponse(
        invocationId: 'invoke-reconcile-001',
        text: 'Runtime answer',
        usage: new Usage(promptTokens: 5, completionTokens: 7),
        meta: new Meta(provider: 'openai', model: 'gpt-4o-mini'),
    );
    $response->messages = new Collection();
    $response->toolCalls = new Collection();
    $response->toolResults = new Collection();
    $response->steps = new Collection();

    $conversation = $bridge->reconcile($projected, $request, $response);

    /** @var ConversationStore $store */
    $store = app(ConversationStore::class);
    $persisted = $store->find(new ConversationId('conv-reconcile-001'));

    expect($conversation?->messageCount())
      ->toBe(2)
      ->and($conversation?->messages[0]->content)->toBe('Current prompt')
      ->and($conversation?->messages[1]->content)->toBe('Runtime answer')
      ->and($persisted?->messageCount())->toBe(2)
      ->and($persisted?->metadata['last_invocation_id'])->toBe('invoke-reconcile-001');
});
