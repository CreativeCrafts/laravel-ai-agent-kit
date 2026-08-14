<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Resilience;

use InvalidArgumentException;

final readonly class RetryAmplificationCalculator
{
    public function estimate(
        ?int $queueAttempts,
        int $pipelineStepAttempts,
        int $providerAttemptsPerExecution,
    ): RetryAmplificationEstimate {
        if ($queueAttempts !== null && $queueAttempts < 1) {
            throw new InvalidArgumentException('Queue attempts must be null or greater than or equal to one.');
        }

        if ($pipelineStepAttempts < 1) {
            throw new InvalidArgumentException('Pipeline step attempts must be greater than or equal to one.');
        }

        if ($providerAttemptsPerExecution < 1) {
            throw new InvalidArgumentException('Provider attempts per execution must be greater than or equal to one.');
        }

        return new RetryAmplificationEstimate(
            queueAttempts: $queueAttempts,
            pipelineStepAttempts: $pipelineStepAttempts,
            providerAttemptsPerExecution: $providerAttemptsPerExecution,
            worstCaseProviderAttempts: $queueAttempts === null
                ? null
                : $queueAttempts * $pipelineStepAttempts * $providerAttemptsPerExecution,
        );
    }
}
