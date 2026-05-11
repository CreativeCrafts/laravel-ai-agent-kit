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
    config()->set('ai-agent-kit.memory.default_driver', 'database');
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
    $conversation = databaseConversation('conv-database');

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

it('persists plaintext payloads when database encryption is explicitly disabled', function (): void {
    config()->set('ai-agent-kit.memory.database.encrypt_payloads', false);

    $store = app(ConversationStore::class);
    $startedAt = new DateTimeImmutable('2026-03-14T09:00:00+00:00');

    $conversation = new Conversation(
        id: new ConversationId('conv-plaintext'),
        createdAt: $startedAt,
        updatedAt: $startedAt,
        messages: [
        new ConversationMessage(
            id: new MessageId('msg-plaintext'),
            role: ConversationMessageRole::User,
            content: 'Plaintext content',
            createdAt: $startedAt,
            metadata: ['channel' => 'cli'],
        ),
      ],
        metadata: ['tenant' => 'internal'],
    );

    $store->save($conversation);

    $conversationRow = DB::table('ai_agent_conversations')
      ->where('conversation_id', 'conv-plaintext')
      ->first();

    $messageRow = DB::table('ai_agent_conversation_messages')
      ->where('message_id', 'msg-plaintext')
      ->first();

    expect($conversationRow)->not
      ->toBeNull()
      ->and($conversationRow?->is_encrypted)->toBe(0)
      ->and((string)$conversationRow?->metadata_ciphertext)->toContain('internal');

    expect($messageRow)->not
      ->toBeNull()
      ->and((string)$messageRow?->content_ciphertext)->toBe('Plaintext content')
      ->and((string)$messageRow?->metadata_ciphertext)->toContain('cli');

    $reloaded = $store->find(new ConversationId('conv-plaintext'));

    expect($reloaded)
      ->toBeInstanceOf(Conversation::class)
      ->and($reloaded?->latestMessage()?->content)->toBe('Plaintext content')
      ->and($reloaded?->metadataValue('tenant'))->toBe('internal');
});

it('saves the same conversation idempotently without duplicate conversation or message rows', function (): void {
    $store = app(ConversationStore::class);
    $conversation = databaseConversation('conv-idempotent');

    $store->save($conversation);
    $store->save($conversation);
    $store->save($conversation);

    $conversationRow = DB::table('ai_agent_conversations')
        ->where('conversation_id', 'conv-idempotent')
        ->first();

    expect(DB::table('ai_agent_conversations')->where('conversation_id', 'conv-idempotent')->count())->toBe(1)
        ->and(DB::table('ai_agent_conversation_messages')->where('conversation_record_id', $conversationRow?->id)->count())->toBe(2)
        ->and(DB::table('ai_agent_conversation_messages')->where('conversation_record_id', $conversationRow?->id)->where('message_id', 'msg-001')->count())->toBe(1)
        ->and(DB::table('ai_agent_conversation_messages')->where('conversation_record_id', $conversationRow?->id)->where('message_id', 'msg-002')->count())->toBe(1);
});

it('preserves the original conversation created_at when saving an existing row', function (): void {
    $store = app(ConversationStore::class);
    $createdAt = new DateTimeImmutable('2026-03-14T09:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-03-14T10:00:00+00:00');
    $conversationId = new ConversationId('conv-created-at-preserved');

    $store->save(databaseConversation('conv-created-at-preserved', $createdAt, $createdAt));
    $store->save(databaseConversation('conv-created-at-preserved', new DateTimeImmutable('2030-01-01T00:00:00+00:00'), $updatedAt));

    $row = DB::table('ai_agent_conversations')->where('conversation_id', $conversationId->toString())->first();
    $reloaded = $store->find($conversationId);

    expect((string)$row?->created_at)->toBe('2026-03-14 09:00:00')
        ->and($reloaded?->createdAt->format('Y-m-d H:i:s'))->toBe('2026-03-14 09:00:00')
        ->and($reloaded?->updatedAt->format('Y-m-d H:i:s'))->toBe('2026-03-14 10:00:00');
});

it('restores a soft-deleted conversation when it is saved again', function (): void {
    $store = app(ConversationStore::class);
    $conversationId = new ConversationId('conv-restore');

    $store->save(databaseConversation('conv-restore'));
    $store->delete($conversationId);

    expect($store->find($conversationId))->toBeNull()
        ->and(DB::table('ai_agent_conversations')->where('conversation_id', 'conv-restore')->whereNotNull('deleted_at')->exists())->toBeTrue();

    $store->save(databaseConversation('conv-restore', updatedAt: new DateTimeImmutable('2026-03-14T10:00:00+00:00')));

    expect($store->find($conversationId))->toBeInstanceOf(Conversation::class)
        ->and(DB::table('ai_agent_conversations')->where('conversation_id', 'conv-restore')->whereNull('deleted_at')->exists())->toBeTrue();
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
      ->and(DB::table('ai_agent_conversations')->where('conversation_id', 'conv-update')->whereNotNull('deleted_at')->exists())->toBeTrue();
});

it('persists existing messages incrementally instead of rewriting unchanged rows', function (): void {
    $store = app(ConversationStore::class);
    $startedAt = new DateTimeImmutable('2026-03-14T09:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-03-14T09:10:00+00:00');
    $conversationId = new ConversationId('conv-incremental');

    $store->save(
        new Conversation(
            id: $conversationId,
            createdAt: $startedAt,
            updatedAt: $startedAt,
            messages: [
          new ConversationMessage(
              id: new MessageId('msg-stable'),
              role: ConversationMessageRole::User,
              content: 'Stable content',
              createdAt: $startedAt,
          ),
        ],
        ),
    );

    $stableRowBefore = DB::table('ai_agent_conversation_messages')->where('message_id', 'msg-stable')->first();

    $store->save(
        new Conversation(
            id: $conversationId,
            createdAt: $startedAt,
            updatedAt: $updatedAt,
            messages: [
          new ConversationMessage(
              id: new MessageId('msg-stable'),
              role: ConversationMessageRole::User,
              content: 'Stable content',
              createdAt: $startedAt,
          ),
          new ConversationMessage(
              id: new MessageId('msg-added'),
              role: ConversationMessageRole::Assistant,
              content: 'Added content',
              createdAt: $updatedAt,
          ),
        ],
        ),
    );

    $stableRowAfter = DB::table('ai_agent_conversation_messages')->where('message_id', 'msg-stable')->first();

    expect($stableRowBefore)
      ->not
      ->toBeNull()
      ->and($stableRowAfter)->not
      ->toBeNull()
      ->and($stableRowBefore?->id)->toBe($stableRowAfter?->id)
      ->and(DB::table('ai_agent_conversation_messages')->where('conversation_record_id', $stableRowAfter?->conversation_record_id)->count())->toBe(2);
});

it('updates message sequence content metadata and attachments using the existing message row', function (): void {
    config()->set('ai-agent-kit.memory.database.encrypt_payloads', false);

    $store = app(ConversationStore::class);
    $startedAt = new DateTimeImmutable('2026-03-14T09:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-03-14T09:10:00+00:00');
    $conversationId = new ConversationId('conv-message-update');

    $store->save(
        new Conversation(
            id: $conversationId,
            createdAt: $startedAt,
            updatedAt: $startedAt,
            messages: [
                new ConversationMessage(
                    id: new MessageId('msg-a'),
                    role: ConversationMessageRole::User,
                    content: 'First content',
                    createdAt: $startedAt,
                    metadata: ['phase' => 'one'],
                ),
                new ConversationMessage(
                    id: new MessageId('msg-b'),
                    role: ConversationMessageRole::Assistant,
                    content: 'Second content',
                    createdAt: $updatedAt,
                    metadata: ['phase' => 'two'],
                ),
            ],
        ),
    );

    $rowBefore = DB::table('ai_agent_conversation_messages')->where('message_id', 'msg-a')->first();

    $store->save(
        new Conversation(
            id: $conversationId,
            createdAt: $startedAt,
            updatedAt: $updatedAt,
            messages: [
                new ConversationMessage(
                    id: new MessageId('msg-b'),
                    role: ConversationMessageRole::Assistant,
                    content: 'Second content',
                    createdAt: $updatedAt,
                    metadata: ['phase' => 'two'],
                ),
                new ConversationMessage(
                    id: new MessageId('msg-a'),
                    role: ConversationMessageRole::Assistant,
                    content: 'Changed content',
                    createdAt: $startedAt,
                    metadata: [
                        'phase' => 'changed',
                        'attachments' => [['type' => 'provider-document', 'id' => 'file-123']],
                    ],
                ),
            ],
        ),
    );

    $rowAfter = DB::table('ai_agent_conversation_messages')->where('message_id', 'msg-a')->first();
    $reloaded = $store->find($conversationId);
    $updatedMessage = $reloaded?->messages[1] ?? null;

    expect($rowBefore)->not->toBeNull()
        ->and($rowAfter)->not->toBeNull()
        ->and($rowAfter?->id)->toBe($rowBefore?->id)
        ->and($rowAfter?->sequence)->toBe(2)
        ->and($rowAfter?->role)->toBe(ConversationMessageRole::Assistant->value)
        ->and((string)$rowAfter?->content_ciphertext)->toBe('Changed content')
        ->and((string)$rowAfter?->metadata_ciphertext)->toContain('changed')
        ->and((string)$rowAfter?->attachments_ciphertext)->toContain('file-123')
        ->and($updatedMessage?->id->toString())->toBe('msg-a')
        ->and($updatedMessage?->metadataValue('phase'))->toBe('changed')
        ->and($updatedMessage?->metadataValue('attachments'))->toBe([['type' => 'provider-document', 'id' => 'file-123']]);
});

it('stores message ids uniquely per conversation record rather than globally', function (): void {
    $store = app(ConversationStore::class);
    $startedAt = new DateTimeImmutable('2026-03-14T09:00:00+00:00');

    $store->save(
        new Conversation(
            id: new ConversationId('conv-message-scope-a'),
            createdAt: $startedAt,
            updatedAt: $startedAt,
            messages: [
                new ConversationMessage(
                    id: new MessageId('shared-message-id'),
                    role: ConversationMessageRole::User,
                    content: 'A content',
                    createdAt: $startedAt,
                ),
            ],
        ),
    );

    $store->save(
        new Conversation(
            id: new ConversationId('conv-message-scope-b'),
            createdAt: $startedAt,
            updatedAt: $startedAt,
            messages: [
                new ConversationMessage(
                    id: new MessageId('shared-message-id'),
                    role: ConversationMessageRole::User,
                    content: 'B content',
                    createdAt: $startedAt,
                ),
            ],
        ),
    );

    expect(DB::table('ai_agent_conversation_messages')->where('message_id', 'shared-message-id')->count())->toBe(2)
        ->and($store->find(new ConversationId('conv-message-scope-a'))?->latestMessage()?->content)->toBe('A content')
        ->and($store->find(new ConversationId('conv-message-scope-b'))?->latestMessage()?->content)->toBe('B content');
});

it('preserves retention timestamp behavior during atomic saves', function (): void {
    $store = app(ConversationStore::class);
    $updatedAt = new DateTimeImmutable('2026-03-14T09:05:00+00:00');

    $store->save(databaseConversation('conv-retention', updatedAt: $updatedAt));

    $row = DB::table('ai_agent_conversations')->where('conversation_id', 'conv-retention')->first();

    expect((string)$row?->retention_until)->toBe('2026-04-13 09:05:00');
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

function databaseConversation(
    string $id,
    ?DateTimeImmutable $createdAt = null,
    ?DateTimeImmutable $updatedAt = null,
): Conversation {
    $createdAt ??= new DateTimeImmutable('2026-03-14T09:00:00+00:00');
    $updatedAt ??= new DateTimeImmutable('2026-03-14T09:05:00+00:00');

    return new Conversation(
        id: new ConversationId($id),
        createdAt: $createdAt,
        updatedAt: $updatedAt,
        messages: [
            new ConversationMessage(
                id: new MessageId('msg-001'),
                role: ConversationMessageRole::User,
                content: 'Summarize the meeting notes.',
                createdAt: $createdAt,
                metadata: [
                    'channel' => 'web',
                    'attachments' => [['type' => 'provider-document', 'id' => 'file-a']],
                ],
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
}
