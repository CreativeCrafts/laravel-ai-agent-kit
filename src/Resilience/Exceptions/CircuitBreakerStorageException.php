<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Resilience\Exceptions;

use RuntimeException;

final class CircuitBreakerStorageException extends RuntimeException
{
    public static function locksUnsupported(string $store): self
    {
        return new self(
            "Circuit breaker cache store [{$store}] must support atomic locks.",
        );
    }

    public static function invalidState(string $key): self
    {
        return new self(
            "Circuit breaker cache state for [{$key}] is invalid.",
        );
    }
}
