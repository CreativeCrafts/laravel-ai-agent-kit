<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Memory;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;

/**
 * Delegates to an inner {@see ConversationStore}; when {@see find} misses, loads legacy
 * Laravel AI `agent_*` tables via {@see LegacyLaravelAiDatabaseConversationReader}.
 */
final readonly class FallingBackToLegacyLaravelAiConversationStore implements ConversationStore
{
    public function __construct(
        private ConversationStore $inner,
        private LegacyLaravelAiDatabaseConversationReader $legacyReader,
    ) {
    }

    public function find(ConversationId $conversationId): ?Conversation
    {
        $conversation = $this->inner->find($conversationId);

        if ($conversation instanceof Conversation) {
            return $conversation;
        }

        return $this->legacyReader->find($conversationId);
    }

    public function save(Conversation $conversation): void
    {
        $this->inner->save($conversation);
    }

    public function delete(ConversationId $conversationId): void
    {
        $this->inner->delete($conversationId);
    }
}
