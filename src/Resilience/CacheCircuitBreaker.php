<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Resilience;

use Closure;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience\CircuitBreaker;
use CreativeCrafts\LaravelAiAgentKit\Resilience\enums\CircuitBreakerState;
use CreativeCrafts\LaravelAiAgentKit\Resilience\Exceptions\CircuitBreakerStorageException;
use CreativeCrafts\LaravelAiAgentKit\Resilience\Exceptions\InvalidCircuitBreakerConfigurationException;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\LockProvider;

final readonly class CacheCircuitBreaker implements CircuitBreaker
{
    public function __construct(
        private string $key,
        private string $cacheKey,
        private Repository $cache,
        private LockProvider $lockProvider,
        private CircuitBreakerThresholds $thresholds,
        private int $lockSeconds,
    ) {
        if ($this->key === '') {
            throw InvalidCircuitBreakerConfigurationException::invalidKey($this->key);
        }
    }

    public function key(): string
    {
        return $this->key;
    }

    public function snapshot(?DateTimeImmutable $now = null): CircuitBreakerSnapshot
    {
        return $this->snapshotFromState(
            $this->readState(),
            $now ?? new DateTimeImmutable(),
        );
    }

    public function allowsExecution(?DateTimeImmutable $now = null): bool
    {
        return $this->snapshot($now)->state !== CircuitBreakerState::Open;
    }

    public function recordSuccess(?DateTimeImmutable $now = null): CircuitBreakerSnapshot
    {
        $resolvedNow = $now ?? new DateTimeImmutable();

        return $this->mutate(function (CacheCircuitBreakerState $state) use ($resolvedNow): void {
            if (!$this->thresholds->enabled) {
                return;
            }

            $breakerState = $this->stateAt($state, $resolvedNow);

            if ($breakerState === CircuitBreakerState::HalfOpen) {
                $state->consecutiveFailures = 0;
                $state->halfOpenSuccesses++;

                if ($state->halfOpenSuccesses >= $this->thresholds->halfOpenSuccessThreshold) {
                    $this->reset($state);
                }

                return;
            }

            if ($breakerState === CircuitBreakerState::Closed) {
                $state->consecutiveFailures = 0;
                $state->halfOpenSuccesses = 0;
                $state->openedAt = null;
            }
        }, $resolvedNow);
    }

    public function recordFailure(?DateTimeImmutable $now = null): CircuitBreakerSnapshot
    {
        $resolvedNow = $now ?? new DateTimeImmutable();

        return $this->mutate(function (CacheCircuitBreakerState $state) use ($resolvedNow): void {
            $state->lastFailureAt = $resolvedNow;

            if (!$this->thresholds->enabled) {
                return;
            }

            $breakerState = $this->stateAt($state, $resolvedNow);

            if ($breakerState === CircuitBreakerState::HalfOpen) {
                $state->consecutiveFailures = $this->thresholds->failureThreshold;
                $state->halfOpenSuccesses = 0;
                $state->openedAt = $resolvedNow;

                return;
            }

            if ($breakerState === CircuitBreakerState::Open) {
                return;
            }

            $state->consecutiveFailures++;
            $state->halfOpenSuccesses = 0;

            if ($state->consecutiveFailures >= $this->thresholds->failureThreshold) {
                $state->openedAt = $resolvedNow;
            }
        }, $resolvedNow);
    }

    /**
     * @param Closure(CacheCircuitBreakerState): void $mutation
     */
    private function mutate(Closure $mutation, DateTimeImmutable $now): CircuitBreakerSnapshot
    {
        $lock = $this->lockProvider->lock($this->cacheKey.':lock', $this->lockSeconds);

        /** @var CircuitBreakerSnapshot $snapshot */
        $snapshot = $lock->block($this->lockSeconds, function () use ($mutation, $now): CircuitBreakerSnapshot {
            $state = $this->readState();
            $mutation($state);
            $this->cache->forever($this->cacheKey, $state);

            return $this->snapshotFromState($state, $now);
        });

        return $snapshot;
    }

    private function readState(): CacheCircuitBreakerState
    {
        $state = $this->cache->get($this->cacheKey);

        if ($state === null) {
            return new CacheCircuitBreakerState();
        }

        if (!$state instanceof CacheCircuitBreakerState) {
            throw CircuitBreakerStorageException::invalidState($this->key);
        }

        return $state;
    }

    private function snapshotFromState(
        CacheCircuitBreakerState $state,
        DateTimeImmutable $now,
    ): CircuitBreakerSnapshot {
        return new CircuitBreakerSnapshot(
            key: $this->key,
            state: $this->stateAt($state, $now),
            consecutiveFailures: $state->consecutiveFailures,
            halfOpenSuccesses: $state->halfOpenSuccesses,
            openedAt: $state->openedAt,
            lastFailureAt: $state->lastFailureAt,
        );
    }

    private function stateAt(
        CacheCircuitBreakerState $state,
        DateTimeImmutable $now,
    ): CircuitBreakerState {
        if (!$this->thresholds->enabled || !$state->openedAt instanceof DateTimeImmutable) {
            return CircuitBreakerState::Closed;
        }

        $resetAt = $state->openedAt->add(
            new DateInterval('PT'.$this->thresholds->resetTimeoutSeconds.'S'),
        );

        return $now >= $resetAt
            ? CircuitBreakerState::HalfOpen
            : CircuitBreakerState::Open;
    }

    private function reset(CacheCircuitBreakerState $state): void
    {
        $state->consecutiveFailures = 0;
        $state->halfOpenSuccesses = 0;
        $state->openedAt = null;
        $state->lastFailureAt = null;
    }
}
