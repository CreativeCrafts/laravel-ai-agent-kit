<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Resilience;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience\CircuitBreaker;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience\CircuitBreakerManager;
use CreativeCrafts\LaravelAiAgentKit\Resilience\Exceptions\InvalidCircuitBreakerConfigurationException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class InMemoryCircuitBreakerManager implements CircuitBreakerManager
{
    /** @var array<string, InMemoryCircuitBreaker> */
    private array $breakers = [];

    private readonly CircuitBreakerThresholds $thresholds;

    public function __construct(
        ConfigRepository $config,
        string $configKey = 'ai-agent-kit',
    ) {
        $breakerConfig = $this->requireArray(
            $config->get($configKey . '.resilience.circuit_breaker', []),
            $configKey . '.resilience.circuit_breaker',
        );

        $this->thresholds = new CircuitBreakerThresholds(
            enabled: $this->boolValue($breakerConfig, 'enabled', true, $configKey . '.resilience.circuit_breaker.enabled'),
            failureThreshold: $this->intValue($breakerConfig, 'failure_threshold', 3, $configKey . '.resilience.circuit_breaker.failure_threshold'),
            resetTimeoutSeconds: $this->intValue($breakerConfig, 'reset_timeout_seconds', 60, $configKey . '.resilience.circuit_breaker.reset_timeout_seconds'),
            halfOpenSuccessThreshold: $this->intValue($breakerConfig, 'half_open_success_threshold', 1, $configKey . '.resilience.circuit_breaker.half_open_success_threshold'),
        );
    }

    public function for(string $key): CircuitBreaker
    {
        if ($key === '') {
            throw InvalidCircuitBreakerConfigurationException::invalidKey($key);
        }

        if (!array_key_exists($key, $this->breakers)) {
            $this->breakers[$key] = new InMemoryCircuitBreaker($key, $this->thresholds);
        }

        return $this->breakers[$key];
    }

    /**
     * @return array<string, mixed>
     */
    private function requireArray(mixed $value, string $key): array
    {
        if (!is_array($value)) {
            throw InvalidCircuitBreakerConfigurationException::invalidConfigType($key, 'array');
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function boolValue(array $config, string $key, bool $default, string $path): bool
    {
        $value = $config[$key] ?? $default;

        if (!is_bool($value)) {
            throw InvalidCircuitBreakerConfigurationException::invalidConfigType($path, 'bool');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function intValue(array $config, string $key, int $default, string $path): int
    {
        $value = $config[$key] ?? $default;

        if (!is_int($value)) {
            throw InvalidCircuitBreakerConfigurationException::invalidConfigType($path, 'int');
        }

        return $value;
    }
}
