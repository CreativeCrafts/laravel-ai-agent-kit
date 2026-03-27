<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\Exceptions;

final class InvalidDelegationTargetException extends OrchestrationException
{
    /**
     * @param list<string> $allowedTargets
     */
    public static function forAgent(string $agentKey, string $targetAgent, array $allowedTargets): self
    {
        $allowed = $allowedTargets === []
          ? 'none'
          : implode(', ', $allowedTargets);

        return new self(
            sprintf(
                'Agent [%s] is not allowed to delegate to [%s]. Allowed targets: [%s].',
                $agentKey,
                $targetAgent,
                $allowed,
            ),
        );
    }
}
