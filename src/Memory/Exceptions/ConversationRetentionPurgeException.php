<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Memory\Exceptions;

use RuntimeException;
use Throwable;

final class ConversationRetentionPurgeException extends RuntimeException
{
    public static function forDriver(string $driverName, Throwable $previous): self
    {
        return new self(
            message: "Conversation retention purge failed for memory driver [{$driverName}].",
            previous: $previous,
        );
    }
}
