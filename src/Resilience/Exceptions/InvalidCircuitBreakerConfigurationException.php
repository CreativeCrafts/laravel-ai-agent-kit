<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Resilience\Exceptions;

use RuntimeException;

final class InvalidCircuitBreakerConfigurationException extends RuntimeException
{
    public static function invalidKey(string $key): self
    {
        return new self("Circuit breaker keys must be non-empty strings. Received [{$key}].");
    }

    public static function invalidFailureThreshold(int $failureThreshold): self
    {
        return new self("Circuit breaker failure_threshold must be >= 1. Received [{$failureThreshold}].");
    }

    public static function invalidResetTimeoutSeconds(int $resetTimeoutSeconds): self
    {
        return new self("Circuit breaker reset_timeout_seconds must be >= 1. Received [{$resetTimeoutSeconds}].");
    }

    public static function invalidHalfOpenSuccessThreshold(int $halfOpenSuccessThreshold): self
    {
        return new self("Circuit breaker half_open_success_threshold must be >= 1. Received [{$halfOpenSuccessThreshold}].");
    }

    public static function invalidConfigType(string $key, string $expected): self
    {
        return new self("Invalid circuit breaker config type for [{$key}]. Expected {$expected}.");
    }
}
