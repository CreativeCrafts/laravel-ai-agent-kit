<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Exceptions;

final class PipelineBudgetExceededException extends PipelineExecutionException
{
    public static function forMaxSteps(int $maxSteps, int $attemptedStepNumber): self
    {
        return new self(
            message: sprintf(
                'Pipeline execution exceeded step budget: attempted step [%d] but max_steps is [%d].',
                $attemptedStepNumber,
                $maxSteps,
            ),
        );
    }

    public static function forTotalTimeout(int $maxTotalTimeoutSeconds, float $elapsedSeconds): self
    {
        return new self(
            message: sprintf(
                'Pipeline execution exceeded total timeout budget: elapsed %.3f seconds exceeds max_total_timeout_seconds [%d].',
                $elapsedSeconds,
                $maxTotalTimeoutSeconds,
            ),
        );
    }
}
