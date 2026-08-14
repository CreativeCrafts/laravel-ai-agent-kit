<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Resilience;

final readonly class RetryAmplificationEstimate
{
    public function __construct(
        public ?int $queueAttempts,
        public int $pipelineStepAttempts,
        public int $providerAttemptsPerExecution,
        public ?int $worstCaseProviderAttempts,
    ) {
    }

    public function isComplete(): bool
    {
        return $this->worstCaseProviderAttempts !== null;
    }
}
