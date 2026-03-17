<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Resilience\Exceptions;

use RuntimeException;

final class InvalidRetryPolicyException extends RuntimeException
{
    public static function invalidAttemptNumber(int $attemptNumber): self
    {
        return new self("Retry attempt numbers must be integers >= 1. Received [{$attemptNumber}].");
    }

    public static function invalidMaxAttempts(int $maxAttempts): self
    {
        return new self("Retry policies require max_attempts >= 1. Received [{$maxAttempts}].");
    }

    public static function invalidMaxRetries(int $maxRetries): self
    {
        return new self("Retry policies require max retries >= 0. Received [{$maxRetries}].");
    }

    public static function invalidBaseDelay(int $baseDelayMs): self
    {
        return new self("Retry backoff base_delay_ms must be >= 0. Received [{$baseDelayMs}].");
    }

    public static function invalidMaxDelay(int $maxDelayMs, int $baseDelayMs): self
    {
        return new self("Retry backoff max_delay_ms must be >= base_delay_ms [{$baseDelayMs}]. Received [{$maxDelayMs}].");
    }

    public static function invalidMultiplier(float $multiplier): self
    {
        return new self("Retry backoff multiplier must be >= 1.0. Received [{$multiplier}].");
    }

    public static function unsupportedStrategy(string $strategy): self
    {
        return new self("Unsupported retry backoff strategy [{$strategy}].");
    }

    public static function invalidConfigType(string $key, string $expected): self
    {
        return new self("Invalid retry config type for [{$key}]. Expected {$expected}.");
    }
}
