<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\RuntimeTelemetryAgent;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\RuntimeAttachmentsReplayed;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Ai;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\Files\Base64Image;
use Laravel\Ai\Files\RemoteImage;
use CreativeCrafts\LaravelAiAgentKit\Core\Config\ConfigValidator;
use CreativeCrafts\LaravelAiAgentKit\Core\Config\Exceptions\InvalidConfigurationException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    config()->set('ai-agent-kit.memory.default_driver', 'database');
    config()->set('ai-agent-kit.memory.database.connection', 'testing');
    config()->set('ai-agent-kit.memory.database.conversations_table', 'ai_agent_conversations');
    config()->set('ai-agent-kit.memory.database.messages_table', 'ai_agent_conversation_messages');
    config()->set('ai-agent-kit.memory.database.driver_name', 'database');
    config()->set('ai-agent-kit.memory.database.retention_days', 30);
    config()->set('ai-agent-kit.memory.database.encrypt_payloads', false);

    Schema::dropIfExists('ai_agent_conversation_messages');
    Schema::dropIfExists('ai_agent_conversations');

    /** @var Migration $createConversations */
    $createConversations = require __DIR__ . '/../database/migrations/create_ai_agent_conversations_table.php.stub';
    /** @var Migration $createMessages */
    $createMessages = require __DIR__ . '/../database/migrations/create_ai_agent_conversation_messages_table.php.stub';

    $createConversations->up();
    $createMessages->up();
});

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

it('persists serialized attachments on the user message row', function (): void {
    app()->register(AiServiceProvider::class);

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['ok'])
      ->preventStrayPrompts();

    $conversationId = new ConversationId('conv-attachments-persist');
    $image = new Base64Image(base64: base64_encode('persist-bytes'), mimeType: 'image/png');

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
      ->and($userMessage?->metadata['attachments'] ?? null)->toBeArray()
      ->and($userMessage?->metadata['attachments'][0]['type'] ?? null)->toBe('base64-image');

    $row = DB::table('ai_agent_conversation_messages')
        ->where('message_id', $userMessage?->id->toString())
        ->first();

    expect($row)->not->toBeNull()
      ->and($row->attachments_ciphertext)->not->toBeNull()
      ->and((string) $row->attachments_ciphertext)->toContain('base64-image');
});

it('merges prior replayable attachments with the current prompt when configured', function (): void {
    config()->set('ai-agent-kit.memory.attachments_replay.enabled', true);
    config()->set('ai-agent-kit.memory.attachments_replay.deny_types', [
        'base64-image',
        'base64-document',
        'base64-audio',
        'local-image',
        'local-document',
        'local-audio',
    ]);

    app()->register(AiServiceProvider::class);

    Ai::fakeAgent(RuntimeTelemetryAgent::class, [
        'First',
        'Second',
    ])->preventStrayPrompts();

    $conversationId = new ConversationId('conv-attachments-merge');
    $prior = new RemoteImage(url: 'https://cdn.example.com/doc.png', mimeType: 'image/png');
    $turn2 = new Base64Image(base64: base64_encode('second-bytes'), mimeType: 'image/png');

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $runtime->execute(
        new ExecutionRequest(
            runId: 'run-attachments-turn-1',
            prompt: 'First turn with remote image.',
            provider: 'openai',
            conversationId: $conversationId,
            storeConversation: true,
            attachments: [$prior],
        ),
    );

    $runtime->execute(
        new ExecutionRequest(
            runId: 'run-attachments-turn-2',
            prompt: 'Second turn.',
            provider: 'openai',
            conversationId: $conversationId,
            storeConversation: true,
            continueConversation: true,
            attachments: [$turn2],
            metadata: ['attachment_replay' => 'merge'],
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt) use ($prior, $turn2): bool {
        if ($prompt->prompt !== 'Second turn.') {
            return true;
        }

        $all = $prompt->attachments->all();

        return count($all) === 2
            && $all[0] instanceof RemoteImage
            && $all[0]->url === $prior->url
            && $all[1] instanceof Base64Image
            && $all[1]->base64 === $turn2->base64;
    });
});

it('dispatches observability when policy excludes persisted attachments', function (): void {
    config()->set('ai-agent-kit.memory.attachments_replay.enabled', true);
    config()->set('ai-agent-kit.memory.attachments_replay.deny_url_substrings', ['evil.example.com']);

    app()->register(AiServiceProvider::class);

    Ai::fakeAgent(RuntimeTelemetryAgent::class, [
        'First',
        'Second',
    ])->preventStrayPrompts();

    Event::fake([RuntimeAttachmentsReplayed::class]);

    $conversationId = new ConversationId('conv-attachments-deny');
    $blocked = new RemoteImage(url: 'https://evil.example.com/leak.png', mimeType: 'image/png');

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $runtime->execute(
        new ExecutionRequest(
            runId: 'run-deny-1',
            prompt: 'First.',
            provider: 'openai',
            conversationId: $conversationId,
            storeConversation: true,
            attachments: [$blocked],
        ),
    );

    $runtime->execute(
        new ExecutionRequest(
            runId: 'run-deny-2',
            prompt: 'Second.',
            provider: 'openai',
            conversationId: $conversationId,
            storeConversation: true,
            continueConversation: true,
            metadata: ['attachment_replay' => 'merge'],
        ),
    );

    Event::assertDispatched(RuntimeAttachmentsReplayed::class, function (RuntimeAttachmentsReplayed $event): bool {
        return $event->excludedCount >= 1
            && $event->exclusions !== []
            && ($event->exclusions[0]['reason'] ?? '') === 'authorization_denied';
    });
});

it('rejects invalid memory.attachments_replay config', function (): void {
    /** @var ConfigValidator $validator */
    $validator = app(ConfigValidator::class);

    $validator->validate([
        'providers' => [
            'null' => ['driver' => 'null', 'enabled' => true, 'options' => []],
        ],
        'default_provider' => 'null',
        'failover_order' => ['null'],
        'budgets' => [
            'max_steps' => 1,
            'max_tool_calls' => 1,
            'max_retries_per_step' => 1,
            'max_total_timeout_seconds' => 1,
            'max_tokens' => null,
            'max_cost_usd' => null,
        ],
        'memory' => [
            'attachments_replay' => [
                'enabled' => 'yes',
            ],
        ],
    ]);
})->throws(InvalidConfigurationException::class, 'memory.attachments_replay.enabled');
