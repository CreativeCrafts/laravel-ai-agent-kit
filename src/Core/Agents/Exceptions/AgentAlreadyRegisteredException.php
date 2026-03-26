<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Agents\Exceptions;

use RuntimeException;

final class AgentAlreadyRegisteredException extends RuntimeException
{
    public static function forKey(string $agentKey, string $existingClass, string $newClass): self
    {
        return new self(
            sprintf(
                'Agent key [%s] is already registered to [%s] and cannot be registered again for [%s].',
                $agentKey,
                $existingClass,
                $newClass,
            ),
        );
    }
}
