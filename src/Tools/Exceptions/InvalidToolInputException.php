<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions;

use RuntimeException;

final class InvalidToolInputException extends RuntimeException
{
    /**
     * @param list<string> $errors
     */
    public static function withErrors(string $toolName, array $errors): self
    {
        return new self(
            sprintf(
                'Tool [%s] input validation failed: %s',
                $toolName,
                implode('; ', $errors),
            ),
        );
    }
}
