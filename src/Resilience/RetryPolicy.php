<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Resilience;

use CreativeCrafts\LaravelAiAgentKit\Resilience\Exceptions\InvalidRetryPolicyException;

final readonly class RetryPolicy
{
    public function __construct(
        public bool $enabled,
        public int $maxAttempts,
        public BackoffStrategyConfig $backoff,
    ) {
        if ($this->maxAttempts < 1) {
            throw InvalidRetryPolicyException::invalidMaxAttempts($this->maxAttempts);
        }
    }

    public function allowsRetryAfterAttempt(int $attemptNumber): bool
    {
        if ($attemptNumber < 1) {
            throw InvalidRetryPolicyException::invalidAttemptNumber($attemptNumber);
        }

        return $this->enabled && $attemptNumber < $this->maxAttempts;
    }

    public function delayForRetry(int $retryNumber): int
    {
        if ($retryNumber < 1) {
            throw InvalidRetryPolicyException::invalidAttemptNumber($retryNumber);
        }

        if (!$this->enabled || $retryNumber > $this->maxRetries()) {
            return 0;
        }

        return $this->backoff->delayForRetry($retryNumber);
    }

    public function maxRetries(): int
    {
        return max(0, $this->maxAttempts - 1);
    }

    public function boundedToMaxRetries(int $maxRetriesPerStep): self
    {
        if ($maxRetriesPerStep < 0) {
            throw InvalidRetryPolicyException::invalidMaxRetries($maxRetriesPerStep);
        }

        if (!$this->enabled) {
            return new self(
                enabled: false,
                maxAttempts: 1,
                backoff: $this->backoff,
            );
        }

        $effectiveMaxRetries = min($this->maxRetries(), $maxRetriesPerStep);

        return new self(
            enabled: $effectiveMaxRetries > 0,
            maxAttempts: $effectiveMaxRetries + 1,
            backoff: $this->backoff,
        );
    }
}
