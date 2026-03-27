<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\Exceptions;

final class OrchestrationStepLimitExceededException extends OrchestrationException
{
    public static function forAgent(string $agentKey, int $step, int $maxSteps): self
    {
        return new self(
            sprintf(
                'Synchronous orchestration step limit exceeded while executing agent [%s]. Current step [%d] exceeds configured maximum steps [%d].',
                $agentKey,
                $step,
                $maxSteps,
            ),
        );
    }
}
