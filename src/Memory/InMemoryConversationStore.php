<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Memory;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationRetentionPurger;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use DateMalformedStringException;
use DateTimeImmutable;
use CreativeCrafts\LaravelAiAgentKit\Memory\Exceptions\ConversationWriteConflictException;

final class InMemoryConversationStore implements ConversationRetentionPurger, ConversationStore
{
    /**
     * @var array<string, Conversation>
     */
    private array $conversations = [];

    public function __construct(
        private readonly ?int $retentionDays = null,
    ) {
    }

    public function find(ConversationId $conversationId): ?Conversation
    {
        return $this->conversations[$conversationId->toString()] ?? null;
    }

    public function save(Conversation $conversation): void
    {
        $key = $conversation->id->toString();
        $stored = $this->conversations[$key] ?? null;

        if (!$stored instanceof Conversation) {
            $this->conversations[$key] = $conversation->withRevision(0);

            return;
        }

        if ($stored->revision !== $conversation->revision) {
            throw ConversationWriteConflictException::forRevision(
                conversationId: $key,
                expectedRevision: $conversation->revision,
                actualRevision: $stored->revision,
            );
        }

        $this->conversations[$key] = $conversation->withRevision($conversation->revision + 1);
    }

    public function delete(ConversationId $conversationId): void
    {
        unset($this->conversations[$conversationId->toString()]);
    }

    /**
     * @throws DateMalformedStringException
     */
    public function purgeExpired(?DateTimeImmutable $now = null): int
    {
        if ($this->retentionDays === null) {
            return 0;
        }

        $threshold = $now ?? new DateTimeImmutable();
        $purged = 0;

        foreach ($this->conversations as $conversationId => $conversation) {
            $expiresAt = $conversation->updatedAt->modify(sprintf('+%d days', $this->retentionDays));

            if ($expiresAt <= $threshold) {
                unset($this->conversations[$conversationId]);
                $purged++;
            }
        }

        return $purged;
    }
}
