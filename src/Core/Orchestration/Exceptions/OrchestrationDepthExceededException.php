<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\Exceptions;

final class OrchestrationDepthExceededException extends OrchestrationException
{
    public static function forAgent(string $agentKey, int $depth, int $maxDepth): self
    {
        return new self(
            sprintf(
                'Synchronous orchestration depth exceeded while executing agent [%s]. Current depth [%d] exceeds configured maximum depth [%d].',
                $agentKey,
                $depth,
                $maxDepth,
            ),
        );
    }
}
