<?php

declare(strict_types=1);

use DateTimeImmutable;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessage;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessageRole;
use CreativeCrafts\LaravelAiAgentKit\Memory\DatabaseConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\MessageId;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('agent_conversation_messages');
    Schema::dropIfExists('agent_conversations');
    Schema::dropIfExists('ai_agent_conversation_messages');
    Schema::dropIfExists('ai_agent_conversations');

    /** @var Migration $laravelAiMigration */
    $laravelAiMigration = require __DIR__ . '/../vendor/laravel/ai/database/migrations/2026_01_11_000001_create_agent_conversations_table.php';
    $laravelAiMigration->up();

    /** @var Migration $createConversations */
    $createConversations = require __DIR__ . '/../database/migrations/create_ai_agent_conversations_table.php.stub';
    /** @var Migration $createMessages */
    $createMessages = require __DIR__ . '/../database/migrations/create_ai_agent_conversation_messages_table.php.stub';

    $createConversations->up();
    $createMessages->up();
});

it('loads legacy Laravel AI conversation rows when package store misses and fallback is enabled', function (): void {
    config()->set('ai-agent-kit.memory.default_driver', 'database');
    config()->set('ai-agent-kit.memory.database.connection', 'testing');
    config()->set('ai-agent-kit.memory.database.conversations_table', 'ai_agent_conversations');
    config()->set('ai-agent-kit.memory.database.messages_table', 'ai_agent_conversation_messages');
    config()->set('ai-agent-kit.memory.database.driver_name', 'database');
    config()->set('ai-agent-kit.memory.database.retention_days', 30);
    config()->set('ai-agent-kit.memory.database.encrypt_payloads', false);
    config()->set('ai-agent-kit.memory.laravel_ai_legacy.enabled', true);
    config()->set('ai-agent-kit.memory.laravel_ai_legacy.connection', 'testing');
    config()->set('ai-agent-kit.memory.laravel_ai_legacy.conversations_table', 'agent_conversations');
    config()->set('ai-agent-kit.memory.laravel_ai_legacy.messages_table', 'agent_conversation_messages');

    $convId = '019b2f00-0000-7000-8000-000000000001';
    $now = now();

    DB::table('agent_conversations')->insert([
        'id' => $convId,
        'user_id' => null,
        'title' => 'Legacy chat',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('agent_conversation_messages')->insert([
        [
            'id' => '019b2f00-0000-7000-8000-000000000011',
            'conversation_id' => $convId,
            'user_id' => null,
            'agent' => 'App\\Agents\\LegacyAgent',
            'role' => 'user',
            'content' => 'Hello from legacy',
            'attachments' => json_encode([]),
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => '019b2f00-0000-7000-8000-000000000012',
            'conversation_id' => $convId,
            'user_id' => null,
            'agent' => 'App\\Agents\\LegacyAgent',
            'role' => 'assistant',
            'content' => 'Legacy reply',
            'attachments' => '[]',
            'tool_calls' => json_encode([
                [
                    'id' => 'call_1',
                    'name' => 'search',
                    'arguments' => ['q' => 'x'],
                    'result_id' => null,
                ],
            ]),
            'tool_results' => json_encode([
                [
                    'id' => 'call_1',
                    'name' => 'search',
                    'arguments' => ['q' => 'x'],
                    'result' => 'ok',
                    'result_id' => null,
                ],
            ]),
            'usage' => json_encode(['prompt_tokens' => 1, 'completion_tokens' => 2]),
            'meta' => json_encode(['provider' => 'openai', 'model' => 'gpt-4o']),
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    $store = app(ConversationStore::class);

    $loaded = $store->find(new ConversationId($convId));

    expect($loaded)->toBeInstanceOf(Conversation::class)
        ->and($loaded->id->toString())->toBe($convId)
        ->and($loaded->metadataValue('laravel_ai'))->toBeArray()
        ->and($loaded->metadataValue('laravel_ai')['title'])->toBe('Legacy chat')
        ->and($loaded->messageCount())->toBe(2)
        ->and($loaded->messages[0]->role)->toBe(ConversationMessageRole::User)
        ->and($loaded->messages[0]->content)->toBe('Hello from legacy')
        ->and($loaded->messages[1]->role)->toBe(ConversationMessageRole::Assistant)
        ->and($loaded->messages[1]->content)->toBe('Legacy reply')
        ->and($loaded->messages[1]->metadataValue('laravel_ai'))->toBeArray()
        ->and($loaded->messages[1]->metadataValue('laravel_ai')['tool_calls'])->toHaveCount(1);
});

it('prefers package database rows over legacy when both exist', function (): void {
    config()->set('ai-agent-kit.memory.default_driver', 'database');
    config()->set('ai-agent-kit.memory.database.connection', 'testing');
    config()->set('ai-agent-kit.memory.database.conversations_table', 'ai_agent_conversations');
    config()->set('ai-agent-kit.memory.database.messages_table', 'ai_agent_conversation_messages');
    config()->set('ai-agent-kit.memory.database.driver_name', 'database');
    config()->set('ai-agent-kit.memory.database.retention_days', 30);
    config()->set('ai-agent-kit.memory.database.encrypt_payloads', false);
    config()->set('ai-agent-kit.memory.laravel_ai_legacy.enabled', true);
    config()->set('ai-agent-kit.memory.laravel_ai_legacy.connection', 'testing');
    config()->set('ai-agent-kit.memory.laravel_ai_legacy.conversations_table', 'agent_conversations');
    config()->set('ai-agent-kit.memory.laravel_ai_legacy.messages_table', 'agent_conversation_messages');

    $convId = '019b2f00-0000-7000-8000-000000000002';
    $now = now();

    DB::table('agent_conversations')->insert([
        'id' => $convId,
        'user_id' => null,
        'title' => 'Legacy only',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('agent_conversation_messages')->insert([
        'id' => '019b2f00-0000-7000-8000-000000000021',
        'conversation_id' => $convId,
        'user_id' => null,
        'agent' => 'X',
        'role' => 'user',
        'content' => 'Legacy user',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $inner = app(DatabaseConversationStore::class);
    $inner->save(new Conversation(
        id: new ConversationId($convId),
        createdAt: new DateTimeImmutable('2026-04-01T10:00:00+00:00'),
        updatedAt: new DateTimeImmutable('2026-04-01T10:00:00+00:00'),
        messages: [
            new ConversationMessage(
                id: new MessageId('pkg-msg-1'),
                role: ConversationMessageRole::User,
                content: 'Package user',
                createdAt: new DateTimeImmutable('2026-04-01T10:00:00+00:00'),
            ),
        ],
        metadata: ['source' => 'package'],
    ));

    $wrapped = app(ConversationStore::class);
    $loaded = $wrapped->find(new ConversationId($convId));

    expect($loaded)->not->toBeNull()
        ->and($loaded->messageCount())->toBe(1)
        ->and($loaded->messages[0]->content)->toBe('Package user')
        ->and($loaded->metadataValue('source'))->toBe('package');
});

it('round-trips new-format conversations through the database store with legacy fallback enabled', function (): void {
    config()->set('ai-agent-kit.memory.default_driver', 'database');
    config()->set('ai-agent-kit.memory.database.connection', 'testing');
    config()->set('ai-agent-kit.memory.database.conversations_table', 'ai_agent_conversations');
    config()->set('ai-agent-kit.memory.database.messages_table', 'ai_agent_conversation_messages');
    config()->set('ai-agent-kit.memory.database.driver_name', 'database');
    config()->set('ai-agent-kit.memory.database.retention_days', 30);
    config()->set('ai-agent-kit.memory.database.encrypt_payloads', false);
    config()->set('ai-agent-kit.memory.laravel_ai_legacy.enabled', true);
    config()->set('ai-agent-kit.memory.laravel_ai_legacy.connection', 'testing');
    config()->set('ai-agent-kit.memory.laravel_ai_legacy.conversations_table', 'agent_conversations');
    config()->set('ai-agent-kit.memory.laravel_ai_legacy.messages_table', 'agent_conversation_messages');

    $store = app(ConversationStore::class);
    $startedAt = new DateTimeImmutable('2026-04-10T12:00:00+00:00');

    $conversation = new Conversation(
        id: new ConversationId('conv-roundtrip-phase5'),
        createdAt: $startedAt,
        updatedAt: $startedAt,
        messages: [
            new ConversationMessage(
                id: new MessageId('rt-1'),
                role: ConversationMessageRole::User,
                content: 'Ping',
                createdAt: $startedAt,
            ),
        ],
        metadata: ['phase' => 5],
    );

    $store->save($conversation);
    $reloaded = $store->find(new ConversationId('conv-roundtrip-phase5'));

    expect($reloaded)->not->toBeNull()
        ->and($reloaded->metadataValue('phase'))->toBe(5)
        ->and($reloaded->messages[0]->content)->toBe('Ping');

    expect(DB::table('agent_conversations')->where('id', 'conv-roundtrip-phase5')->exists())->toBeFalse();
});
