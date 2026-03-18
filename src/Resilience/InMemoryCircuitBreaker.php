<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Resilience;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience\CircuitBreaker;
use CreativeCrafts\LaravelAiAgentKit\Resilience\enums\CircuitBreakerState;
use CreativeCrafts\LaravelAiAgentKit\Resilience\Exceptions\InvalidCircuitBreakerConfigurationException;
use DateInterval;
use DateMalformedIntervalStringException;
use DateTimeImmutable;

final class InMemoryCircuitBreaker implements CircuitBreaker
{
    private int $consecutiveFailures = 0;

    private int $halfOpenSuccesses = 0;

    private ?DateTimeImmutable $openedAt = null;

    private ?DateTimeImmutable $lastFailureAt = null;

    public function __construct(
        private readonly string $key,
        private readonly CircuitBreakerThresholds $thresholds,
    ) {
        if ($this->key === '') {
            throw InvalidCircuitBreakerConfigurationException::invalidKey($this->key);
        }
    }

    public function key(): string
    {
        return $this->key;
    }

    /**
     * @throws DateMalformedIntervalStringException
     */
    public function allowsExecution(?DateTimeImmutable $now = null): bool
    {
        $resolvedNow = $now ?? new DateTimeImmutable();

        return $this->stateAt($resolvedNow) !== CircuitBreakerState::Open;
    }

    /**
     * @throws DateMalformedIntervalStringException
     */
    public function recordSuccess(?DateTimeImmutable $now = null): CircuitBreakerSnapshot
    {
        $resolvedNow = $now ?? new DateTimeImmutable();

        if (!$this->thresholds->enabled) {
            return $this->snapshot($resolvedNow);
        }

        $state = $this->stateAt($resolvedNow);

        if ($state === CircuitBreakerState::HalfOpen) {
            $this->consecutiveFailures = 0;
            $this->halfOpenSuccesses++;

            if ($this->halfOpenSuccesses >= $this->thresholds->halfOpenSuccessThreshold) {
                $this->reset();
            }

            return $this->snapshot($resolvedNow);
        }

        if ($state === CircuitBreakerState::Closed) {
            $this->consecutiveFailures = 0;
            $this->halfOpenSuccesses = 0;
            $this->openedAt = null;
        }

        return $this->snapshot($resolvedNow);
    }

    /**
     * @throws DateMalformedIntervalStringException
     */
    public function snapshot(?DateTimeImmutable $now = null): CircuitBreakerSnapshot
    {
        $resolvedNow = $now ?? new DateTimeImmutable();

        return new CircuitBreakerSnapshot(
            key: $this->key,
            state: $this->stateAt($resolvedNow),
            consecutiveFailures: $this->consecutiveFailures,
            halfOpenSuccesses: $this->halfOpenSuccesses,
            openedAt: $this->openedAt,
            lastFailureAt: $this->lastFailureAt,
        );
    }

    /**
     * @throws DateMalformedIntervalStringException
     */
    public function recordFailure(?DateTimeImmutable $now = null): CircuitBreakerSnapshot
    {
        $resolvedNow = $now ?? new DateTimeImmutable();
        $this->lastFailureAt = $resolvedNow;

        if (!$this->thresholds->enabled) {
            return $this->snapshot($resolvedNow);
        }

        $state = $this->stateAt($resolvedNow);

        if ($state === CircuitBreakerState::HalfOpen) {
            $this->consecutiveFailures = $this->thresholds->failureThreshold;
            $this->halfOpenSuccesses = 0;
            $this->openedAt = $resolvedNow;

            return $this->snapshot($resolvedNow);
        }

        if ($state === CircuitBreakerState::Open) {
            return $this->snapshot($resolvedNow);
        }

        $this->consecutiveFailures++;
        $this->halfOpenSuccesses = 0;

        if ($this->consecutiveFailures >= $this->thresholds->failureThreshold) {
            $this->openedAt = $resolvedNow;
        }

        return $this->snapshot($resolvedNow);
    }

    /**
     * @throws DateMalformedIntervalStringException
     */
    private function stateAt(DateTimeImmutable $now): CircuitBreakerState
    {
        if (!$this->thresholds->enabled) {
            return CircuitBreakerState::Closed;
        }

        if (!$this->openedAt instanceof DateTimeImmutable) {
            return CircuitBreakerState::Closed;
        }

        $resetAt = $this->openedAt->add(new DateInterval('PT' . $this->thresholds->resetTimeoutSeconds . 'S'));

        if ($now >= $resetAt) {
            return CircuitBreakerState::HalfOpen;
        }

        return CircuitBreakerState::Open;
    }

    private function reset(): void
    {
        $this->consecutiveFailures = 0;
        $this->halfOpenSuccesses = 0;
        $this->openedAt = null;
        $this->lastFailureAt = null;
    }
}
