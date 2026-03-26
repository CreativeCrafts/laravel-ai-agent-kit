<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Agents\Exceptions;

use RuntimeException;

final class DuplicateAgentKeyException extends RuntimeException
{
    /**
     * @param class-string $existingAgentClass
     * @param class-string $newAgentClass
     */
    public static function forKey(string $agentKey, string $existingAgentClass, string $newAgentClass): self
    {
        return new self(
            sprintf(
                'Agent key [%s] is already registered by [%s] and cannot also be registered by [%s].',
                $agentKey,
                $existingAgentClass,
                $newAgentClass,
            ),
        );
    }
}
