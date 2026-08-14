<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Exceptions;

use CreativeCrafts\LaravelAiAgentKit\Observability\Contracts\HasFailureCategory;
use CreativeCrafts\LaravelAiAgentKit\Observability\Support\FailureCategory;

final class PipelineBudgetExceededException extends PipelineExecutionException implements HasFailureCategory
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

    public static function forUnaffordableRetryDelay(
        int $maxTotalTimeoutSeconds,
        float $elapsedSeconds,
        int $delayMilliseconds,
    ): self {
        return new self(
            message: sprintf(
                'Pipeline retry delay [%dms] would exceed max_total_timeout_seconds [%d] after %.3f elapsed seconds.',
                $delayMilliseconds,
                $maxTotalTimeoutSeconds,
                $elapsedSeconds,
            ),
        );
    }

    public function failureCategory(): string
    {
        return FailureCategory::BudgetExceeded->value;
    }
}
