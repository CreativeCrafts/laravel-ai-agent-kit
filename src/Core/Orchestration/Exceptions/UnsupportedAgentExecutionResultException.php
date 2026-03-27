<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\Exceptions;

final class UnsupportedAgentExecutionResultException extends OrchestrationException
{
    /**
     * @param list<string> $supportedKinds
     */
    public static function forKind(string $agentKey, string $kind, array $supportedKinds): self
    {
        return new self(
            sprintf(
                'Agent [%s] returned unsupported execution result kind [%s] for the synchronous orchestrator. Supported kinds: [%s].',
                $agentKey,
                $kind,
                implode(', ', $supportedKinds),
            ),
        );
    }
}
