<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Agents\Exceptions;

use RuntimeException;

final class AgentNotRegisteredException extends RuntimeException
{
    public static function forKey(string $agentKey): self
    {
        return new self("Agent [{$agentKey}] is not registered.");
    }
}
