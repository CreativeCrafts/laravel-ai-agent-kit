<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions;

use RuntimeException;

final class ConversationContextBridgeException extends RuntimeException
{
    public static function missingConversationIdForContinuation(string $runId): self
    {
        return new self(
            sprintf(
                'Runtime conversation continuation for run [%s] requires a non-empty conversation ID.',
                $runId,
            ),
        );
    }
}
