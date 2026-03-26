<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Agents\Exceptions;

use RuntimeException;

final class InvalidAgentRegistrationException extends RuntimeException
{
    /**
     * @param class-string $agentClass
     */
    public static function classDoesNotExist(string $agentClass): self
    {
        return new self("Agent class [{$agentClass}] does not exist.");
    }

    /**
     * @param class-string $agentClass
     */
    public static function mustImplementAgentContract(string $agentClass): self
    {
        return new self("Resolved class [{$agentClass}] must implement the Agent contract.");
    }
}
