<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Memory;

use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;

interface ConversationStore
{
    public function find(ConversationId $conversationId): ?Conversation;

    public function save(Conversation $conversation): void;

    public function delete(ConversationId $conversationId): void;
}
