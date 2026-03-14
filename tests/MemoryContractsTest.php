<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessage;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessageRole;
use CreativeCrafts\LaravelAiAgentKit\Memory\MessageId;

it('models conversation identifiers and messages with explicit typed semantics', function () {
    $createdAt = new DateTimeImmutable('2026-03-14T09:00:00+00:00');

    $message = new ConversationMessage(
        id: new MessageId('msg-001'),
        role: ConversationMessageRole::User,
        content: 'Summarize the last meeting.',
        createdAt: $createdAt,
        metadata: ['channel' => 'web'],
    );

    $conversation = new Conversation(
        id: new ConversationId('conv-001'),
        createdAt: $createdAt,
        updatedAt: $createdAt,
        messages: [$message],
        metadata: ['tenant' => 'creativecrafts'],
    );

    expect($conversation->id->toString())
        ->toBe('conv-001')
        ->and($conversation->messageCount())->toBe(1)
        ->and($conversation->isEmpty())->toBeFalse()
        ->and($conversation->latestMessage()?->id->toString())->toBe('msg-001')
        ->and($conversation->latestMessage()?->role)->toBe(ConversationMessageRole::User)
        ->and($conversation->latestMessage()?->metadataValue('channel'))->toBe('web')
        ->and($conversation->metadataValue('tenant'))->toBe('creativecrafts');
});

it('keeps conversation aggregates immutable when appending messages or metadata', function () {
    $startedAt = new DateTimeImmutable('2026-03-14T09:00:00+00:00');
    $nextMessageAt = new DateTimeImmutable('2026-03-14T09:05:00+00:00');

    $conversation = new Conversation(
        id: new ConversationId('conv-immutable'),
        createdAt: $startedAt,
        updatedAt: $startedAt,
    );

    $updatedConversation = $conversation
        ->withAppendedMessage(
            new ConversationMessage(
                id: new MessageId('msg-002'),
                role: ConversationMessageRole::Assistant,
                content: 'Here is the summary.',
                createdAt: $nextMessageAt,
            ),
        )
        ->withMetadataValue('status', 'summarized', $nextMessageAt);

    expect($conversation->messages)
        ->toBe([])
        ->and($conversation->metadata)->toBe([])
        ->and($conversation->updatedAt->format(DATE_ATOM))->toBe($startedAt->format(DATE_ATOM))
        ->and($updatedConversation->messageCount())->toBe(1)
        ->and($updatedConversation->latestMessage()?->role)->toBe(ConversationMessageRole::Assistant)
        ->and($updatedConversation->metadataValue('status'))->toBe('summarized')
        ->and($updatedConversation->updatedAt->format(DATE_ATOM))->toBe($nextMessageAt->format(DATE_ATOM));
});

it('supports conversation storage through a vendor-neutral memory contract', function () {
    $store = new class () implements ConversationStore {
        /**
         * @var array<string, Conversation>
         */
        private array $conversations = [];

        public function find(ConversationId $conversationId): ?Conversation
        {
            return $this->conversations[$conversationId->toString()] ?? null;
        }

        public function save(Conversation $conversation): void
        {
            $this->conversations[$conversation->id->toString()] = $conversation;
        }

        public function delete(ConversationId $conversationId): void
        {
            unset($this->conversations[$conversationId->toString()]);
        }
    };

    $createdAt = new DateTimeImmutable('2026-03-14T09:00:00+00:00');
    $conversationId = new ConversationId('conv-store');
    $conversation = new Conversation(
        id: $conversationId,
        createdAt: $createdAt,
        updatedAt: $createdAt,
        messages: [
            new ConversationMessage(
                id: new MessageId('msg-store'),
                role: ConversationMessageRole::System,
                content: 'Conversation started.',
                createdAt: $createdAt,
            ),
        ],
    );

    $store->save($conversation);

    $storedConversation = $store->find($conversationId);

    expect($storedConversation)
        ->toBeInstanceOf(Conversation::class)
        ->and($storedConversation?->id->equals($conversationId))->toBeTrue()
        ->and($storedConversation?->latestMessage()?->content)->toBe('Conversation started.');

    $store->delete($conversationId);

    expect($store->find($conversationId))->toBeNull();
});
