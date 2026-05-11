<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationRetentionPurger;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessage;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessageRole;
use CreativeCrafts\LaravelAiAgentKit\Memory\MessageId;
use CreativeCrafts\LaravelAiAgentKit\Memory\RedisConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fakes\FakeRedisConnection;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fakes\FakeRedisManager;

beforeEach(function (): void {
    config()->set('ai-agent-kit.memory.default_driver', 'redis');
    config()->set('ai-agent-kit.memory.redis.connection', 'default');
    config()->set('ai-agent-kit.memory.redis.prefix', 'ai_agent_memory:');
    config()->set('ai-agent-kit.memory.redis.driver_name', 'redis');
    config()->set('ai-agent-kit.memory.redis.retention_days', 30);
    config()->set('ai-agent-kit.memory.redis.encrypt_payloads', true);

    app()->forgetInstance(ConversationStore::class);
    app()->forgetInstance(ConversationRetentionPurger::class);
    app()->forgetInstance(RedisConversationStore::class);
    app()->forgetInstance('redis');

    app()->singleton('redis', fn (): FakeRedisManager => new FakeRedisManager());
});

it('binds the redis conversation store and retention purger contracts', function (): void {
    expect(app(ConversationStore::class))
      ->toBeInstanceOf(RedisConversationStore::class)
      ->and(app(ConversationRetentionPurger::class))->toBeInstanceOf(RedisConversationStore::class);
});

it('fails fast when redis memory driver is configured but redis manager binding is missing', function (): void {
    app()->forgetInstance(ConversationStore::class);
    app()->forgetInstance(ConversationRetentionPurger::class);
    app()->forgetInstance(RedisConversationStore::class);
    app()->forgetInstance('redis');
    app()->offsetUnset('redis');

    app(ConversationStore::class);
})->throws(RuntimeException::class, 'requires a bound [redis] service');

it('persists and reloads conversations through the redis-backed store', function (): void {
    $store = app(ConversationStore::class);
    $conversation = redisConversation('conv-redis');

    $store->save($conversation);

    $reloaded = $store->find(new ConversationId('conv-redis'));

    expect($reloaded)
      ->toBeInstanceOf(Conversation::class)
      ->and($reloaded?->id->toString())->toBe('conv-redis')
      ->and($reloaded?->messageCount())->toBe(2)
      ->and($reloaded?->latestMessage()?->content)->toBe('Here is the shared summary.')
      ->and($reloaded?->latestMessage()?->metadataValue('driver'))->toBe('redis')
      ->and($reloaded?->metadataValue('scope'))->toBe('shared');
});

it('writes encrypted redis payloads by default and still round-trips through find', function (): void {
    $store = app(ConversationStore::class);

    $store->save(redisConversation('conv-redis-encrypted'));

    $stored = redisConnection()->get('ai_agent_memory:conv-redis-encrypted');

    expect($stored)->toBeString()
        ->and($stored)->not->toContain('Summarize the shared memory state.')
        ->and($stored)->not->toContain('Here is the shared summary.')
        ->and($stored)->not->toContain('shared')
        ->and(json_decode((string)$stored, true))->toMatchArray(['encrypted' => true]);

    $reloaded = $store->find(new ConversationId('conv-redis-encrypted'));

    expect($reloaded)->toBeInstanceOf(Conversation::class)
        ->and($reloaded?->latestMessage()?->content)->toBe('Here is the shared summary.');
});

it('reads legacy plaintext redis payloads for compatibility', function (): void {
    $store = app(ConversationStore::class);
    $conversation = redisConversation('conv-redis-legacy');
    $legacyStore = new RedisConversationStore(
        app: app(),
        connectionName: 'default',
        keyPrefix: 'ai_agent_memory:',
        driverName: 'redis',
        retentionDays: 30,
        encryptPayloads: false,
    );

    $legacyStore->save($conversation);

    $stored = redisConnection()->get('ai_agent_memory:conv-redis-legacy');

    expect($stored)->toBeString()
        ->and($stored)->toContain('Summarize the shared memory state.')
        ->and(json_decode((string)$stored, true))->toHaveKey('conversation_id');

    $reloaded = $store->find(new ConversationId('conv-redis-legacy'));

    expect($reloaded)->toBeInstanceOf(Conversation::class)
        ->and($reloaded?->id->toString())->toBe('conv-redis-legacy')
        ->and($reloaded?->latestMessage()?->content)->toBe('Here is the shared summary.');
});

it('preserves plaintext redis payload behavior when encryption is disabled', function (): void {
    config()->set('ai-agent-kit.memory.redis.encrypt_payloads', false);
    app()->forgetInstance(ConversationStore::class);
    app()->forgetInstance(ConversationRetentionPurger::class);
    app()->forgetInstance(RedisConversationStore::class);

    $store = app(ConversationStore::class);

    $store->save(redisConversation('conv-redis-plaintext'));

    $stored = redisConnection()->get('ai_agent_memory:conv-redis-plaintext');

    expect($stored)->toBeString()
        ->and($stored)->toContain('Summarize the shared memory state.')
        ->and(json_decode((string)$stored, true))->toHaveKey('conversation_id')
        ->and(json_decode((string)$stored, true))->not->toHaveKey('encrypted');
});

it('sets redis native expiration when retention is configured', function (): void {
    $store = app(ConversationStore::class);

    $store->save(redisConversation('conv-redis-ttl'));

    $ttl = redisConnection()->ttlFor('ai_agent_memory:conv-redis-ttl');
    $setCommand = redisConnection()->recordedCommands()[0] ?? null;

    expect($ttl)->toBeInt()
        ->and($ttl)->toBeGreaterThanOrEqual(1)
        ->and($setCommand['name'] ?? null)->toBe('SET')
        ->and($setCommand['arguments'][2] ?? null)->toBe('EX')
        ->and($setCommand['arguments'][3] ?? null)->toBe($ttl);
});

it('omits redis native expiration when retention is disabled', function (): void {
    config()->set('ai-agent-kit.memory.redis.retention_days', null);
    app()->forgetInstance(ConversationStore::class);
    app()->forgetInstance(ConversationRetentionPurger::class);
    app()->forgetInstance(RedisConversationStore::class);

    $store = app(ConversationStore::class);

    $store->save(redisConversation('conv-redis-no-ttl'));

    $setCommand = redisConnection()->recordedCommands()[0] ?? null;

    expect(redisConnection()->ttlFor('ai_agent_memory:conv-redis-no-ttl'))->toBeNull()
        ->and($setCommand['name'] ?? null)->toBe('SET')
        ->and($setCommand['arguments'])->toHaveCount(2);
});

it('uses a minimum redis ttl of one second for past retention timestamps', function (): void {
    config()->set('ai-agent-kit.memory.redis.retention_days', 1);
    app()->forgetInstance(ConversationStore::class);
    app()->forgetInstance(ConversationRetentionPurger::class);
    app()->forgetInstance(RedisConversationStore::class);

    $store = app(ConversationStore::class);

    $store->save(redisConversation('conv-redis-past-ttl', new DateTimeImmutable('2020-01-01T09:00:00+00:00')));

    expect(redisConnection()->ttlFor('ai_agent_memory:conv-redis-past-ttl'))->toBe(1);
});

it('deletes expired redis payloads on find as a lazy expiration safety net', function (): void {
    config()->set('ai-agent-kit.memory.redis.retention_days', 1);
    app()->forgetInstance(ConversationStore::class);
    app()->forgetInstance(ConversationRetentionPurger::class);
    app()->forgetInstance(RedisConversationStore::class);

    $store = app(ConversationStore::class);
    $conversationId = new ConversationId('conv-redis-lazy-expired');

    $store->save(redisConversation('conv-redis-lazy-expired', new DateTimeImmutable('2020-01-01T09:00:00+00:00')));

    expect(redisConnection()->get('ai_agent_memory:conv-redis-lazy-expired'))->toBeString()
        ->and($store->find($conversationId))->toBeNull()
        ->and(redisConnection()->get('ai_agent_memory:conv-redis-lazy-expired'))->toBeNull();
});

it('preserves delete semantics through the shared redis memory contract', function (): void {
    $store = app(ConversationStore::class);
    $startedAt = new DateTimeImmutable('2036-05-14T09:00:00+00:00');
    $conversationId = new ConversationId('conv-redis-delete');

    $store->save(
        new Conversation(
            id: $conversationId,
            createdAt: $startedAt,
            updatedAt: $startedAt,
        ),
    );

    expect($store->find($conversationId))->toBeInstanceOf(Conversation::class);

    $store->delete($conversationId);

    expect($store->find($conversationId))->toBeNull();
});

it('purges expired conversations from the redis driver without real network access', function (): void {
    $store = app(ConversationStore::class);
    $purger = app(ConversationRetentionPurger::class);

    $store->save(
        new Conversation(
            id: new ConversationId('conv-redis-expired'),
            createdAt: new DateTimeImmutable('2026-01-01T07:00:00+00:00'),
            updatedAt: new DateTimeImmutable('2026-01-01T08:00:00+00:00'),
            messages: [
          new ConversationMessage(
              id: new MessageId('msg-redis-expired'),
              role: ConversationMessageRole::User,
              content: 'Expired redis content',
              createdAt: new DateTimeImmutable('2026-01-01T08:00:00+00:00'),
          ),
        ],
        ),
    );

    $store->save(
        new Conversation(
            id: new ConversationId('conv-redis-active'),
            createdAt: new DateTimeImmutable('2036-05-14T07:00:00+00:00'),
            updatedAt: new DateTimeImmutable('2036-05-14T08:00:00+00:00'),
            messages: [
          new ConversationMessage(
              id: new MessageId('msg-redis-active'),
              role: ConversationMessageRole::Assistant,
              content: 'Active redis content',
              createdAt: new DateTimeImmutable('2036-05-14T08:00:00+00:00'),
          ),
        ],
        ),
    );

    $purgedCount = $purger->purgeExpired(new DateTimeImmutable('2026-03-01T00:00:00+00:00'));

    expect($purgedCount)
      ->toBe(1)
      ->and($store->find(new ConversationId('conv-redis-expired')))->toBeNull()
      ->and($store->find(new ConversationId('conv-redis-active')))->toBeInstanceOf(Conversation::class);
});

function redisConnection(): FakeRedisConnection
{
    return app('redis')->connection('default');
}

function redisConversation(string $id, ?DateTimeImmutable $updatedAt = null): Conversation
{
    $startedAt = new DateTimeImmutable('2036-05-14T09:00:00+00:00');
    $updatedAt ??= new DateTimeImmutable('2036-05-14T09:05:00+00:00');

    return new Conversation(
        id: new ConversationId($id),
        createdAt: $startedAt,
        updatedAt: $updatedAt,
        messages: [
            new ConversationMessage(
                id: new MessageId("{$id}-msg-user"),
                role: ConversationMessageRole::User,
                content: 'Summarize the shared memory state.',
                createdAt: $startedAt,
                metadata: [
                    'channel' => 'worker',
                    'attachments' => [['type' => 'provider-document', 'id' => 'file-secret']],
                ],
            ),
            new ConversationMessage(
                id: new MessageId("{$id}-msg-assistant"),
                role: ConversationMessageRole::Assistant,
                content: 'Here is the shared summary.',
                createdAt: $updatedAt,
                metadata: ['driver' => 'redis'],
            ),
        ],
        metadata: ['scope' => 'shared'],
    );
}
