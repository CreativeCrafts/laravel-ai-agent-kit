<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Memory\Exceptions;

use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use RuntimeException;

class ConversationNotFoundException extends RuntimeException
{
    public static function forId(ConversationId $conversationId): self
    {
        return new self("Conversation [{$conversationId->toString()}] was not found.");
    }
}
