<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessage;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessageRole;
use CreativeCrafts\LaravelAiAgentKit\Memory\DatabaseConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\MessageId;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config()->set('ai-agent-kit.memory.default_driver', 'database');
    config()->set('ai-agent-kit.memory.database.connection', 'testing');
    config()->set('ai-agent-kit.memory.database.conversations_table', 'ai_agent_conversations');
    config()->set('ai-agent-kit.memory.database.messages_table', 'ai_agent_conversation_messages');
    config()->set('ai-agent-kit.memory.database.driver_name', 'database');
    config()->set('ai-agent-kit.memory.database.retention_days', 30);
    config()->set('ai-agent-kit.memory.database.encrypt_payloads', false);

    app()->forgetInstance(ConversationStore::class);
    app()->forgetInstance(DatabaseConversationStore::class);

    Schema::dropIfExists('ai_agent_conversation_messages');
    Schema::dropIfExists('ai_agent_conversations');

    /** @var Migration $createConversations */
    $createConversations = require __DIR__ . '/../database/migrations/create_ai_agent_conversations_table.php.stub';
    $createConversations->up();

    createLegacyConversationMessagesTable();
});

it('upgrades the legacy global message id index to the composite message identity index', function (): void {
    expect(sqliteIndexExists('ai_agent_conversation_messages_message_id_unique'))->toBeTrue()
        ->and(sqliteIndexExists('ai_agent_conversation_messages_record_message_unique'))->toBeFalse();

    runMessageIdentityUpgradeMigration();

    expect(sqliteIndexExists('ai_agent_conversation_messages_message_id_unique'))->toBeFalse()
        ->and(sqliteIndexExists('ai_agent_conversation_messages_record_message_unique'))->toBeTrue()
        ->and(sqliteIndexExists('ai_agent_conversation_messages_record_sequence_unique'))->toBeTrue();
});

it('runs defensively when the composite message identity index already exists', function (): void {
    runMessageIdentityUpgradeMigration();
    runMessageIdentityUpgradeMigration();

    expect(sqliteIndexExists('ai_agent_conversation_messages_record_message_unique'))->toBeTrue()
        ->and(sqliteIndexExists('ai_agent_conversation_messages_record_sequence_unique'))->toBeTrue();
});

it('allows the same message id to be saved in different conversations after the upgrade migration', function (): void {
    runMessageIdentityUpgradeMigration();

    $store = app(ConversationStore::class);
    $startedAt = new DateTimeImmutable('2026-05-12T09:00:00+00:00');

    $store->save(conversationWithSharedMessageId('conv-upgraded-a', 'A content', $startedAt));
    $store->save(conversationWithSharedMessageId('conv-upgraded-b', 'B content', $startedAt));

    expect(DB::table('ai_agent_conversation_messages')->where('message_id', 'shared-message-id')->count())->toBe(2)
        ->and($store->find(new ConversationId('conv-upgraded-a'))?->latestMessage()?->content)->toBe('A content')
        ->and($store->find(new ConversationId('conv-upgraded-b'))?->latestMessage()?->content)->toBe('B content');
});

function createLegacyConversationMessagesTable(): void
{
    Schema::create('ai_agent_conversation_messages', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('conversation_record_id')
            ->constrained('ai_agent_conversations')
            ->cascadeOnDelete();
        $table->string('message_id')->unique('ai_agent_conversation_messages_message_id_unique');
        $table->unsignedInteger('sequence');
        $table->string('role', 32)->index();
        $table->longText('content_ciphertext');
        $table->longText('attachments_ciphertext')->nullable();
        $table->longText('metadata_ciphertext')->nullable();
        $table->unsignedInteger('token_count')->nullable();
        $table->timestamp('created_at');
        $table->timestamp('updated_at')->nullable();

        $table->unique(
            ['conversation_record_id', 'sequence'],
            'ai_agent_conversation_messages_record_sequence_unique',
        );
        $table->index(
            ['conversation_record_id', 'created_at'],
            'ai_agent_conversation_messages_record_created_index',
        );
    });
}

function runMessageIdentityUpgradeMigration(): void
{
    /** @var Migration $migration */
    $migration = require __DIR__ . '/../database/migrations/update_ai_agent_conversation_messages_message_identity_index.php.stub';
    $migration->up();
}

function sqliteIndexExists(string $indexName): bool
{
    foreach (DB::select('PRAGMA index_list(ai_agent_conversation_messages)') as $index) {
        if (($index->name ?? null) === $indexName) {
            return true;
        }
    }

    return false;
}

function conversationWithSharedMessageId(string $conversationId, string $content, DateTimeImmutable $createdAt): Conversation
{
    return new Conversation(
        id: new ConversationId($conversationId),
        createdAt: $createdAt,
        updatedAt: $createdAt,
        messages: [
            new ConversationMessage(
                id: new MessageId('shared-message-id'),
                role: ConversationMessageRole::User,
                content: $content,
                createdAt: $createdAt,
            ),
        ],
    );
}
