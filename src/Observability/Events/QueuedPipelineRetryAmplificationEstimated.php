<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

use CreativeCrafts\LaravelAiAgentKit\Resilience\RetryAmplificationEstimate;

final readonly class QueuedPipelineRetryAmplificationEstimated
{
    public function __construct(
        public string $runId,
        public ?int $queueAttempts,
        public int $pipelineStepAttempts,
        public int $providerAttemptsPerExecution,
        public ?int $worstCaseProviderAttempts,
        public bool $complete,
    ) {
    }

    public static function fromEstimate(string $runId, RetryAmplificationEstimate $estimate): self
    {
        return new self(
            runId: $runId,
            queueAttempts: $estimate->queueAttempts,
            pipelineStepAttempts: $estimate->pipelineStepAttempts,
            providerAttemptsPerExecution: $estimate->providerAttemptsPerExecution,
            worstCaseProviderAttempts: $estimate->worstCaseProviderAttempts,
            complete: $estimate->isComplete(),
        );
    }
}
