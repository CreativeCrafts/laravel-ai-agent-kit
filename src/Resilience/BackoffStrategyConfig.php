<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Resilience;

use CreativeCrafts\LaravelAiAgentKit\Resilience\enums\BackoffStrategy;
use CreativeCrafts\LaravelAiAgentKit\Resilience\Exceptions\InvalidRetryPolicyException;

final readonly class BackoffStrategyConfig
{
    public function __construct(
        public BackoffStrategy $strategy,
        public int $baseDelayMs = 0,
        public int $maxDelayMs = 0,
        public float $multiplier = 2.0,
    ) {
        if ($this->baseDelayMs < 0) {
            throw InvalidRetryPolicyException::invalidBaseDelay($this->baseDelayMs);
        }

        if ($this->maxDelayMs < $this->baseDelayMs) {
            throw InvalidRetryPolicyException::invalidMaxDelay($this->maxDelayMs, $this->baseDelayMs);
        }

        if ($this->multiplier < 1.0) {
            throw InvalidRetryPolicyException::invalidMultiplier($this->multiplier);
        }
    }

    public function delayForRetry(int $retryNumber): int
    {
        if ($retryNumber < 1) {
            throw InvalidRetryPolicyException::invalidAttemptNumber($retryNumber);
        }

        $rawDelay = match ($this->strategy) {
            BackoffStrategy::Constant => $this->baseDelayMs,
            BackoffStrategy::Linear => $this->baseDelayMs * $retryNumber,
            BackoffStrategy::Exponential => (int)round($this->baseDelayMs * ($this->multiplier ** ($retryNumber - 1))),
        };

        return min($rawDelay, $this->maxDelayMs);
    }
}
