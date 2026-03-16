<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationRetentionPurger;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessage;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessageRole;
use CreativeCrafts\LaravelAiAgentKit\Memory\InMemoryConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\MessageId;

beforeEach(function (): void {
    config()->set('ai-agent-kit.memory.default_driver', 'in_memory');
    config()->set('ai-agent-kit.memory.in_memory.retention_days', 30);
});

it('binds the in-memory conversation store and retention purger contracts', function (): void {
    expect(app(ConversationStore::class))
      ->toBeInstanceOf(InMemoryConversationStore::class)
      ->and(app(ConversationRetentionPurger::class))->toBeInstanceOf(InMemoryConversationStore::class);
});

it('stores and reloads conversations without persistence', function (): void {
    $store = app(ConversationStore::class);
    $startedAt = new DateTimeImmutable('2026-03-14T09:00:00+00:00');

    $conversation = new Conversation(
        id: new ConversationId('conv-memory'),
        createdAt: $startedAt,
        updatedAt: $startedAt,
        messages: [
        new ConversationMessage(
            id: new MessageId('msg-memory'),
            role: ConversationMessageRole::User,
            content: 'Ephemeral prompt',
            createdAt: $startedAt,
            metadata: ['channel' => 'cli'],
        ),
      ],
        metadata: ['scope' => 'test'],
    );

    $store->save($conversation);

    $reloaded = $store->find(new ConversationId('conv-memory'));

    expect($reloaded)
      ->toBeInstanceOf(Conversation::class)
      ->and($reloaded?->id->toString())->toBe('conv-memory')
      ->and($reloaded?->messageCount())->toBe(1)
      ->and($reloaded?->latestMessage()?->content)->toBe('Ephemeral prompt')
      ->and($reloaded?->latestMessage()?->metadataValue('channel'))->toBe('cli')
      ->and($reloaded?->metadataValue('scope'))->toBe('test');
});

it('preserves delete semantics through the shared memory contract', function (): void {
    $store = app(ConversationStore::class);
    $startedAt = new DateTimeImmutable('2026-03-14T09:00:00+00:00');
    $conversationId = new ConversationId('conv-delete');

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

it('purges expired conversations from the in-memory driver', function (): void {
    $store = app(ConversationStore::class);
    $purger = app(ConversationRetentionPurger::class);

    $store->save(
        new Conversation(
            id: new ConversationId('conv-expired-memory'),
            createdAt: new DateTimeImmutable('2026-01-01T07:00:00+00:00'),
            updatedAt: new DateTimeImmutable('2026-01-01T08:00:00+00:00'),
            messages: [
          new ConversationMessage(
              id: new MessageId('msg-expired-memory'),
              role: ConversationMessageRole::User,
              content: 'Expired ephemeral content',
              createdAt: new DateTimeImmutable('2026-01-01T08:00:00+00:00'),
          ),
        ],
        ),
    );

    $store->save(
        new Conversation(
            id: new ConversationId('conv-active-memory'),
            createdAt: new DateTimeImmutable('2026-03-14T07:00:00+00:00'),
            updatedAt: new DateTimeImmutable('2026-03-14T08:00:00+00:00'),
            messages: [
          new ConversationMessage(
              id: new MessageId('msg-active-memory'),
              role: ConversationMessageRole::Assistant,
              content: 'Active ephemeral content',
              createdAt: new DateTimeImmutable('2026-03-14T08:00:00+00:00'),
          ),
        ],
        ),
    );

    $purgedCount = $purger->purgeExpired(new DateTimeImmutable('2026-03-01T00:00:00+00:00'));

    expect($purgedCount)
      ->toBe(1)
      ->and($store->find(new ConversationId('conv-expired-memory')))->toBeNull()
      ->and($store->find(new ConversationId('conv-active-memory')))->toBeInstanceOf(Conversation::class);
});
