<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\RuntimeTelemetryAgent;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use Laravel\Ai\Ai;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\Files\Base64Image;

it('forwards attachments on the current call user message', function (): void {
    app()->register(AiServiceProvider::class);

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['Image acknowledged'])
      ->preventStrayPrompts();

    $image = new Base64Image(base64: base64_encode('fake-image-bytes'), mimeType: 'image/png');

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $runtime->execute(
        new ExecutionRequest(
            runId: 'run-attachments-current',
            prompt: 'Describe the attached image.',
            provider: 'openai',
            attachments: [$image],
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt) use ($image): bool {
        $attachments = $prompt->attachments->all();

        return count($attachments) === 1 && $attachments[0] === $image;
    });
});

it('does not include attachments on the prompt when none are supplied', function (): void {
    app()->register(AiServiceProvider::class);

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['Plain response'])
      ->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $runtime->execute(
        new ExecutionRequest(
            runId: 'run-attachments-empty',
            prompt: 'No attachments here.',
            provider: 'openai',
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        return $prompt->attachments->isEmpty();
    });
});

it('does not persist attachments into the conversation memory bridge', function (): void {
    app()->register(AiServiceProvider::class);

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['Image acknowledged'])
      ->preventStrayPrompts();

    $conversationId = new ConversationId('conv-attachments-001');
    $image = new Base64Image(base64: base64_encode('fake-image-bytes'), mimeType: 'image/png');

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $runtime->execute(
        new ExecutionRequest(
            runId: 'run-attachments-store',
            prompt: 'First turn with an image.',
            provider: 'openai',
            conversationId: $conversationId,
            storeConversation: true,
            attachments: [$image],
        ),
    );

    /** @var ConversationStore $store */
    $store = app(ConversationStore::class);
    $conversation = $store->find($conversationId);

    $userMessage = $conversation?->messages[0];

    expect($conversation?->messageCount())->toBe(2)
      ->and($userMessage?->content)->toBe('First turn with an image.')
      ->and($userMessage?->metadata)->not->toHaveKey('attachments');
});

it('does not replay prior attachments when continuing a conversation', function (): void {
    app()->register(AiServiceProvider::class);

    Ai::fakeAgent(RuntimeTelemetryAgent::class, [
      'First turn response',
      'Second turn response',
    ])->preventStrayPrompts();

    $conversationId = new ConversationId('conv-attachments-continuation');
    $image = new Base64Image(base64: base64_encode('first-turn-bytes'), mimeType: 'image/png');

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $runtime->execute(
        new ExecutionRequest(
            runId: 'run-attachments-turn-1',
            prompt: 'First turn with an image.',
            provider: 'openai',
            conversationId: $conversationId,
            storeConversation: true,
            attachments: [$image],
        ),
    );

    $runtime->execute(
        new ExecutionRequest(
            runId: 'run-attachments-turn-2',
            prompt: 'Second turn — text only.',
            provider: 'openai',
            conversationId: $conversationId,
            storeConversation: true,
            continueConversation: true,
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        if ($prompt->prompt !== 'Second turn — text only.') {
            return true;
        }

        return $prompt->attachments->isEmpty();
    });
});
