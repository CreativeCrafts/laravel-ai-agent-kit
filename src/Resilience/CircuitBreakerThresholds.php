<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Resilience;

use CreativeCrafts\LaravelAiAgentKit\Resilience\Exceptions\InvalidCircuitBreakerConfigurationException;

final readonly class CircuitBreakerThresholds
{
    public function __construct(
        public bool $enabled,
        public int $failureThreshold,
        public int $resetTimeoutSeconds,
        public int $halfOpenSuccessThreshold,
    ) {
        if ($this->failureThreshold < 1) {
            throw InvalidCircuitBreakerConfigurationException::invalidFailureThreshold($this->failureThreshold);
        }

        if ($this->resetTimeoutSeconds < 1) {
            throw InvalidCircuitBreakerConfigurationException::invalidResetTimeoutSeconds($this->resetTimeoutSeconds);
        }

        if ($this->halfOpenSuccessThreshold < 1) {
            throw InvalidCircuitBreakerConfigurationException::invalidHalfOpenSuccessThreshold($this->halfOpenSuccessThreshold);
        }
    }
}
