<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Resilience;

use CreativeCrafts\LaravelAiAgentKit\Resilience\enums\CircuitBreakerState;
use DateTimeImmutable;

final readonly class CircuitBreakerSnapshot
{
    public function __construct(
        public string $key,
        public CircuitBreakerState $state,
        public int $consecutiveFailures,
        public int $halfOpenSuccesses,
        public ?DateTimeImmutable $openedAt,
        public ?DateTimeImmutable $lastFailureAt,
    ) {
    }
}
