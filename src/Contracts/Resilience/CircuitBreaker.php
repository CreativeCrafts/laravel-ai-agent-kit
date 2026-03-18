<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience;

use CreativeCrafts\LaravelAiAgentKit\Resilience\CircuitBreakerSnapshot;
use DateTimeImmutable;

interface CircuitBreaker
{
    public function key(): string;

    public function snapshot(?DateTimeImmutable $now = null): CircuitBreakerSnapshot;

    public function allowsExecution(?DateTimeImmutable $now = null): bool;

    public function recordSuccess(?DateTimeImmutable $now = null): CircuitBreakerSnapshot;

    public function recordFailure(?DateTimeImmutable $now = null): CircuitBreakerSnapshot;
}
