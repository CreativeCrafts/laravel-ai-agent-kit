<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationRetentionPurger;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessage;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessageRole;
use CreativeCrafts\LaravelAiAgentKit\Memory\DatabaseConversationRetentionPurger;
use CreativeCrafts\LaravelAiAgentKit\Memory\DatabaseConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\MessageId;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config()->set('ai-agent-kit.memory.database.connection', 'testing');
    config()->set('ai-agent-kit.memory.database.conversations_table', 'ai_agent_conversations');
    config()->set('ai-agent-kit.memory.database.messages_table', 'ai_agent_conversation_messages');
    config()->set('ai-agent-kit.memory.database.driver_name', 'database');
    config()->set('ai-agent-kit.memory.database.retention_days', 30);
    config()->set('ai-agent-kit.memory.database.encrypt_payloads', true);

    Schema::dropIfExists('ai_agent_conversation_messages');
    Schema::dropIfExists('ai_agent_conversations');

    /** @var Migration $createConversations */
    $createConversations = require __DIR__ . '/../database/migrations/create_ai_agent_conversations_table.php.stub';
    /** @var Migration $createMessages */
    $createMessages = require __DIR__ . '/../database/migrations/create_ai_agent_conversation_messages_table.php.stub';

    $createConversations->up();
    $createMessages->up();
});

it('binds the database conversation store and retention purger contracts', function (): void {
    expect(app(ConversationStore::class))
      ->toBeInstanceOf(DatabaseConversationStore::class)
      ->and(app(ConversationRetentionPurger::class))->toBeInstanceOf(DatabaseConversationRetentionPurger::class);
});

it('persists and reloads conversations through the database-backed store', function (): void {
    $store = app(ConversationStore::class);
    $startedAt = new DateTimeImmutable('2026-03-14T09:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-03-14T09:05:00+00:00');

    $conversation = new Conversation(
        id: new ConversationId('conv-database'),
        createdAt: $startedAt,
        updatedAt: $updatedAt,
        messages: [
        new ConversationMessage(
            id: new MessageId('msg-001'),
            role: ConversationMessageRole::User,
            content: 'Summarize the meeting notes.',
            createdAt: $startedAt,
            metadata: ['channel' => 'web'],
        ),
        new ConversationMessage(
            id: new MessageId('msg-002'),
            role: ConversationMessageRole::Assistant,
            content: 'Here is the summary.',
            createdAt: $updatedAt,
            metadata: ['model' => 'null'],
        ),
      ],
        metadata: ['tenant' => 'creativecrafts'],
    );

    $store->save($conversation);

    $reloaded = $store->find(new ConversationId('conv-database'));

    expect($reloaded)
      ->toBeInstanceOf(Conversation::class)
      ->and($reloaded?->id->toString())->toBe('conv-database')
      ->and($reloaded?->messageCount())->toBe(2)
      ->and($reloaded?->latestMessage()?->content)->toBe('Here is the summary.')
      ->and($reloaded?->latestMessage()?->metadataValue('model'))->toBe('null')
      ->and($reloaded?->metadataValue('tenant'))->toBe('creativecrafts');

    $conversationRow = DB::table('ai_agent_conversations')
      ->where('conversation_id', 'conv-database')
      ->first();

    expect($conversationRow)->not
      ->toBeNull()
      ->and($conversationRow?->is_encrypted)->toBe(1)
      ->and((string)$conversationRow?->metadata_ciphertext)->not->toContain('creativecrafts');

    $messageRow = DB::table('ai_agent_conversation_messages')
      ->where('message_id', 'msg-001')
      ->first();

    expect($messageRow)->not
      ->toBeNull()
      ->and((string)$messageRow?->content_ciphertext)->not->toContain('Summarize the meeting notes.');
});

it('updates existing conversations and preserves delete semantics through the contract', function (): void {
    $store = app(ConversationStore::class);
    $startedAt = new DateTimeImmutable('2026-03-14T09:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-03-14T09:10:00+00:00');
    $conversationId = new ConversationId('conv-update');

    $store->save(
        new Conversation(
            id: $conversationId,
            createdAt: $startedAt,
            updatedAt: $startedAt,
            messages: [
          new ConversationMessage(
              id: new MessageId('msg-original'),
              role: ConversationMessageRole::User,
              content: 'Original content',
              createdAt: $startedAt,
          ),
        ],
        ),
    );

    $store->save(
        new Conversation(
            id: $conversationId,
            createdAt: $startedAt,
            updatedAt: $updatedAt,
            messages: [
          new ConversationMessage(
              id: new MessageId('msg-replaced'),
              role: ConversationMessageRole::Assistant,
              content: 'Replacement content',
              createdAt: $updatedAt,
          ),
        ],
            metadata: ['status' => 'updated'],
        ),
    );

    $reloaded = $store->find($conversationId);

    expect($reloaded?->messageCount())
      ->toBe(1)
      ->and($reloaded?->latestMessage()?->id->toString())->toBe('msg-replaced')
      ->and($reloaded?->metadataValue('status'))->toBe('updated');

    $store->delete($conversationId);

    expect($store->find($conversationId))
      ->toBeNull()
      ->and(DB::table('ai_agent_conversation_messages')->count())->toBe(0);
});

it('purges expired conversations according to the configured retention window', function (): void {
    $store = app(ConversationStore::class);
    $purger = app(ConversationRetentionPurger::class);

    $expiredUpdatedAt = new DateTimeImmutable('2026-01-01T08:00:00+00:00');
    $activeUpdatedAt = new DateTimeImmutable('2026-03-14T08:00:00+00:00');

    $store->save(
        new Conversation(
            id: new ConversationId('conv-expired'),
            createdAt: new DateTimeImmutable('2026-01-01T07:00:00+00:00'),
            updatedAt: $expiredUpdatedAt,
            messages: [
          new ConversationMessage(
              id: new MessageId('msg-expired'),
              role: ConversationMessageRole::User,
              content: 'Expired content',
              createdAt: $expiredUpdatedAt,
          ),
        ],
        ),
    );

    $store->save(
        new Conversation(
            id: new ConversationId('conv-active'),
            createdAt: new DateTimeImmutable('2026-03-14T07:00:00+00:00'),
            updatedAt: $activeUpdatedAt,
            messages: [
          new ConversationMessage(
              id: new MessageId('msg-active'),
              role: ConversationMessageRole::Assistant,
              content: 'Active content',
              createdAt: $activeUpdatedAt,
          ),
        ],
        ),
    );

    $purgedCount = $purger->purgeExpired(new DateTimeImmutable('2026-03-01T00:00:00+00:00'));

    expect($purgedCount)
      ->toBe(1)
      ->and($store->find(new ConversationId('conv-expired')))->toBeNull()
      ->and($store->find(new ConversationId('conv-active')))->toBeInstanceOf(Conversation::class)
      ->and(
          DB::table('ai_agent_conversation_messages')
          ->where('message_id', 'msg-expired')
          ->count(),
      )->toBe(0);
});
