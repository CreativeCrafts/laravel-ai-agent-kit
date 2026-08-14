<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Resilience;

use DateTimeImmutable;

final class CacheCircuitBreakerState
{
    public function __construct(
        public int $consecutiveFailures = 0,
        public int $halfOpenSuccesses = 0,
        public ?DateTimeImmutable $openedAt = null,
        public ?DateTimeImmutable $lastFailureAt = null,
    ) {
    }
}
