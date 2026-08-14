<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Resilience;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience\CircuitBreaker;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience\CircuitBreakerManager;
use CreativeCrafts\LaravelAiAgentKit\Resilience\Exceptions\CircuitBreakerStorageException;
use CreativeCrafts\LaravelAiAgentKit\Resilience\Exceptions\InvalidCircuitBreakerConfigurationException;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class CacheCircuitBreakerManager implements CircuitBreakerManager
{
    /** @var array<string, CacheCircuitBreaker> */
    private array $breakers = [];

    private readonly Repository $cache;

    private readonly LockProvider $lockProvider;

    private readonly CircuitBreakerThresholds $thresholds;

    private readonly string $keyPrefix;

    private readonly int $lockSeconds;

    public function __construct(
        CacheFactory $cacheFactory,
        ConfigRepository $config,
        string $configKey = 'ai-agent-kit',
    ) {
        $path = $configKey.'.resilience.circuit_breaker';
        $breakerConfig = $this->requireArray($config->get($path, []), $path);
        $cacheStore = $this->nullableStringValue($breakerConfig, 'cache_store', null, $path.'.cache_store');
        $repository = $cacheFactory->store($cacheStore);

        if (!$repository instanceof Repository) {
            throw CircuitBreakerStorageException::locksUnsupported($cacheStore ?? 'default');
        }

        $store = $repository->getStore();

        if (!$store instanceof LockProvider) {
            throw CircuitBreakerStorageException::locksUnsupported($cacheStore ?? 'default');
        }

        $this->cache = $repository;
        $this->lockProvider = $store;
        $this->keyPrefix = $this->stringValue(
            $breakerConfig,
            'key_prefix',
            'ai-agent-kit:circuit-breaker:',
            $path.'.key_prefix',
        );
        $this->lockSeconds = $this->intValue($breakerConfig, 'lock_seconds', 5, $path.'.lock_seconds');
        $this->thresholds = new CircuitBreakerThresholds(
            enabled: $this->boolValue($breakerConfig, 'enabled', true, $path.'.enabled'),
            failureThreshold: $this->intValue($breakerConfig, 'failure_threshold', 3, $path.'.failure_threshold'),
            resetTimeoutSeconds: $this->intValue($breakerConfig, 'reset_timeout_seconds', 60, $path.'.reset_timeout_seconds'),
            halfOpenSuccessThreshold: $this->intValue($breakerConfig, 'half_open_success_threshold', 1, $path.'.half_open_success_threshold'),
        );
    }

    public function for(string $key): CircuitBreaker
    {
        if ($key === '') {
            throw InvalidCircuitBreakerConfigurationException::invalidKey($key);
        }

        return $this->breakers[$key] ??= new CacheCircuitBreaker(
            key: $key,
            cacheKey: $this->keyPrefix.hash('sha256', $key),
            cache: $this->cache,
            lockProvider: $this->lockProvider,
            thresholds: $this->thresholds,
            lockSeconds: $this->lockSeconds,
        );
    }

    /** @return array<string, mixed> */
    private function requireArray(mixed $value, string $path): array
    {
        if (!is_array($value)) {
            throw InvalidCircuitBreakerConfigurationException::invalidConfigType($path, 'array');
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw InvalidCircuitBreakerConfigurationException::invalidConfigType($path, 'string-keyed array');
            }

            $normalized[$key] = $item;
        }

        return $normalized;
    }

    /** @param array<string, mixed> $config */
    private function boolValue(array $config, string $key, bool $default, string $path): bool
    {
        $value = $config[$key] ?? $default;

        if (!is_bool($value)) {
            throw InvalidCircuitBreakerConfigurationException::invalidConfigType($path, 'bool');
        }

        return $value;
    }

    /** @param array<string, mixed> $config */
    private function intValue(array $config, string $key, int $default, string $path): int
    {
        $value = $config[$key] ?? $default;

        if (!is_int($value) || $value < 1) {
            throw InvalidCircuitBreakerConfigurationException::invalidConfigType($path, 'int >= 1');
        }

        return $value;
    }

    /** @param array<string, mixed> $config */
    private function stringValue(array $config, string $key, string $default, string $path): string
    {
        $value = $config[$key] ?? $default;

        if (!is_string($value) || $value === '') {
            throw InvalidCircuitBreakerConfigurationException::invalidConfigType($path, 'non-empty string');
        }

        return $value;
    }

    /** @param array<string, mixed> $config */
    private function nullableStringValue(
        array $config,
        string $key,
        ?string $default,
        string $path,
    ): ?string {
        $value = $config[$key] ?? $default;

        if ($value !== null && (!is_string($value) || $value === '')) {
            throw InvalidCircuitBreakerConfigurationException::invalidConfigType($path, 'null or non-empty string');
        }

        return $value;
    }
}
