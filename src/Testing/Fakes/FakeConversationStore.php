<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Testing\Fakes;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationRetentionPurger;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use DateMalformedStringException;
use DateTimeImmutable;

final class FakeConversationStore implements ConversationRetentionPurger, ConversationStore
{
    /**
     * @var array<string, Conversation>
     */
    private array $conversations = [];

    /**
     * @var list<string>
     */
    private array $deletedConversationIds = [];

    /**
     * @var list<int>
     */
    private array $purgeCounts = [];

    /**
     * @param iterable<Conversation> $conversations
     */
    public function __construct(
        private readonly ?int $retentionDays = null,
        iterable $conversations = [],
    ) {
        foreach ($conversations as $conversation) {
            $this->save($conversation);
        }
    }

    public function save(Conversation $conversation): void
    {
        $this->conversations[$conversation->id->toString()] = $conversation;
    }

    public function find(ConversationId $conversationId): ?Conversation
    {
        return $this->conversations[$conversationId->toString()] ?? null;
    }

    public function delete(ConversationId $conversationId): void
    {
        unset($this->conversations[$conversationId->toString()]);
        $this->deletedConversationIds[] = $conversationId->toString();
    }

    /**
     * @throws DateMalformedStringException
     */
    public function purgeExpired(?DateTimeImmutable $now = null): int
    {
        if ($this->retentionDays === null) {
            $this->purgeCounts[] = 0;

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

        $this->purgeCounts[] = $purged;

        return $purged;
    }

    /**
     * @return array<string, Conversation>
     */
    public function all(): array
    {
        return $this->conversations;
    }

    /**
     * @return list<string>
     */
    public function deletedConversationIds(): array
    {
        return $this->deletedConversationIds;
    }

    /**
     * @return list<int>
     */
    public function purgeCounts(): array
    {
        return $this->purgeCounts;
    }
}
