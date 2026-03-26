<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Agents\Exceptions;

use RuntimeException;

final class InvalidAgentClassException extends RuntimeException
{
    public static function forClass(string $agentClass, string $reason): self
    {
        return new self(sprintf('Agent class [%s] is invalid: %s', $agentClass, $reason));
    }
}
