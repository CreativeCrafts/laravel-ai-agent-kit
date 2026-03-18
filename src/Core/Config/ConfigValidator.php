<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Config;

use CreativeCrafts\LaravelAiAgentKit\Core\Config\Exceptions\InvalidConfigurationException;
use CreativeCrafts\LaravelAiAgentKit\Resilience\enums\BackoffStrategy;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final readonly class ConfigValidator
{
    public function __construct(
        private ConfigRepository $config,
        private string $configKey = 'ai-agent-kit',
    ) {
    }

    /**
     * Validate the currently loaded config (fail-fast).
     *
     * @throws InvalidConfigurationException
     */
    public function validateCurrentConfig(): void
    {
        $config = $this->config->get($this->configKey);

        if ($config === null) {
            throw InvalidConfigurationException::missingKey($this->configKey);
        }

        if (!is_array($config)) {
            throw InvalidConfigurationException::invalidType($this->configKey, 'array');
        }

        /** @var array<string, mixed> $config */
        $this->validate($config);
    }

    /**
     * @param array<string, mixed> $config
     * @throws InvalidConfigurationException
     */
    public function validate(array $config): void
    {
        $this->validateValidationSection($config);

        $providers = $this->requireArray($config, 'providers');

        if ($providers === []) {
            throw InvalidConfigurationException::invalidValue('providers', 'At least one provider must be configured.');
        }

        foreach ($providers as $name => $provider) {
            if (!is_string($name) || $name === '') {
                throw InvalidConfigurationException::invalidValue('providers', 'Provider names must be non-empty strings.');
            }

            if (!is_array($provider)) {
                throw InvalidConfigurationException::invalidType("providers.{$name}", 'array');
            }

            $driver = $provider['driver'] ?? null;
            if (!is_string($driver) || $driver === '') {
                throw InvalidConfigurationException::invalidValue("providers.{$name}.driver", 'Must be a non-empty string.');
            }

            if (array_key_exists('enabled', $provider) && !is_bool($provider['enabled'])) {
                throw InvalidConfigurationException::invalidType("providers.{$name}.enabled", 'bool');
            }

            if (array_key_exists('options', $provider) && !is_array($provider['options'])) {
                throw InvalidConfigurationException::invalidType("providers.{$name}.options", 'array');
            }
        }

        $defaultProvider = $config['default_provider'] ?? null;
        if (!is_string($defaultProvider) || $defaultProvider === '') {
            throw InvalidConfigurationException::invalidValue('default_provider', 'Must be a non-empty string.');
        }

        if (!array_key_exists($defaultProvider, $providers)) {
            throw InvalidConfigurationException::invalidValue('default_provider', 'Must reference an entry in providers.');
        }

        if (!$this->isProviderEnabled($providers, $defaultProvider)) {
            throw InvalidConfigurationException::invalidValue('default_provider', 'Default provider must be enabled.');
        }

        $failover = $this->requireArray($config, 'failover_order');

        if ($failover === []) {
            throw InvalidConfigurationException::invalidValue('failover_order', 'Must contain at least the default provider.');
        }

        $seen = [];
        foreach ($failover as $idx => $providerName) {
            if (!is_string($providerName) || $providerName === '') {
                throw InvalidConfigurationException::invalidValue("failover_order.{$idx}", 'Must be a non-empty string provider name.');
            }

            if (isset($seen[$providerName])) {
                throw InvalidConfigurationException::invalidValue('failover_order', "Duplicate provider '{$providerName}' is not allowed.");
            }

            $seen[$providerName] = true;

            if (!array_key_exists($providerName, $providers)) {
                throw InvalidConfigurationException::invalidValue('failover_order', "Provider '{$providerName}' is not defined in providers.");
            }

            if (!$this->isProviderEnabled($providers, $providerName)) {
                throw InvalidConfigurationException::invalidValue('failover_order', "Provider '{$providerName}' is disabled but included in failover_order.");
            }
        }

        if (!isset($seen[$defaultProvider])) {
            throw InvalidConfigurationException::invalidValue('failover_order', 'Must include the default_provider.');
        }

        $this->validateBudgets($config);
        $this->validateResilience($config);
        $this->validateMemory($config);
        $this->validateSummarization($config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validateValidationSection(array $config): void
    {
        if (!array_key_exists('validation', $config)) {
            return;
        }

        if (!is_array($config['validation'])) {
            throw InvalidConfigurationException::invalidType('validation', 'array');
        }

        if (array_key_exists('enabled', $config['validation']) && !is_bool($config['validation']['enabled'])) {
            throw InvalidConfigurationException::invalidType('validation.enabled', 'bool');
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return array<int|string, mixed>
     */
    private function requireArray(array $config, string $key): array
    {
        if (!array_key_exists($key, $config)) {
            throw InvalidConfigurationException::missingKey($key);
        }

        if (!is_array($config[$key])) {
            throw InvalidConfigurationException::invalidType($key, 'array');
        }

        return $config[$key];
    }

    /**
     * @param array<int|string, mixed> $providers
     */
    private function isProviderEnabled(array $providers, string $providerName): bool
    {
        $provider = $providers[$providerName] ?? null;

        if (!is_array($provider)) {
            return false;
        }

        $enabled = $provider['enabled'] ?? true;

        if (!is_bool($enabled)) {
            return false;
        }

        return $enabled;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validateBudgets(array $config): void
    {
        if (!array_key_exists('budgets', $config)) {
            return;
        }

        if (!is_array($config['budgets'])) {
            throw InvalidConfigurationException::invalidType('budgets', 'array');
        }

        $intKeys = [
          'max_steps',
          'max_tool_calls',
          'max_retries_per_step',
          'max_total_timeout_seconds',
        ];

        foreach ($intKeys as $key) {
            if (!array_key_exists($key, $config['budgets'])) {
                continue;
            }

            $value = $config['budgets'][$key];
            if (!is_int($value) || $value < 1) {
                throw InvalidConfigurationException::invalidValue("budgets.{$key}", 'Must be an integer >= 1.');
            }
        }

        foreach (['max_tokens', 'max_cost_usd'] as $key) {
            if (!array_key_exists($key, $config['budgets'])) {
                continue;
            }

            $value = $config['budgets'][$key];

            if ($value === null) {
                continue;
            }

            if (!is_int($value) && !is_float($value)) {
                throw InvalidConfigurationException::invalidType("budgets.{$key}", 'int|float|null');
            }

            if ($value < 0) {
                throw InvalidConfigurationException::invalidValue("budgets.{$key}", 'Must be >= 0.');
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validateResilience(array $config): void
    {
        if (!array_key_exists('resilience', $config)) {
            return;
        }

        if (!is_array($config['resilience'])) {
            throw InvalidConfigurationException::invalidType('resilience', 'array');
        }

        $resilience = $config['resilience'];

        if (array_key_exists('retry', $resilience)) {
            if (!is_array($resilience['retry'])) {
                throw InvalidConfigurationException::invalidType('resilience.retry', 'array');
            }

            $retry = $resilience['retry'];

            if (array_key_exists('enabled', $retry) && !is_bool($retry['enabled'])) {
                throw InvalidConfigurationException::invalidType('resilience.retry.enabled', 'bool');
            }

            if (array_key_exists('max_attempts', $retry)) {
                $maxAttempts = $retry['max_attempts'];

                if (!is_int($maxAttempts) || $maxAttempts < 1) {
                    throw InvalidConfigurationException::invalidValue('resilience.retry.max_attempts', 'Must be an integer >= 1.');
                }
            }

            if (array_key_exists('backoff', $retry)) {
                if (!is_array($retry['backoff'])) {
                    throw InvalidConfigurationException::invalidType('resilience.retry.backoff', 'array');
                }

                $backoff = $retry['backoff'];

                if (array_key_exists('strategy', $backoff)) {
                    $strategy = $backoff['strategy'];

                    if (!is_string($strategy) || BackoffStrategy::tryFrom($strategy) === null) {
                        throw InvalidConfigurationException::invalidValue(
                            'resilience.retry.backoff.strategy',
                            'Must be one of: constant, linear, exponential.',
                        );
                    }
                }

                if (array_key_exists('base_delay_ms', $backoff)) {
                    $baseDelay = $backoff['base_delay_ms'];

                    if (!is_int($baseDelay) || $baseDelay < 0) {
                        throw InvalidConfigurationException::invalidValue('resilience.retry.backoff.base_delay_ms', 'Must be an integer >= 0.');
                    }
                }

                if (array_key_exists('max_delay_ms', $backoff)) {
                    $maxDelay = $backoff['max_delay_ms'];

                    if (!is_int($maxDelay) || $maxDelay < 0) {
                        throw InvalidConfigurationException::invalidValue('resilience.retry.backoff.max_delay_ms', 'Must be an integer >= 0.');
                    }

                    $baseDelay = $backoff['base_delay_ms'] ?? 0;
                    if (is_int($baseDelay) && $maxDelay < $baseDelay) {
                        throw InvalidConfigurationException::invalidValue(
                            'resilience.retry.backoff.max_delay_ms',
                            'Must be greater than or equal to resilience.retry.backoff.base_delay_ms.',
                        );
                    }
                }

                if (array_key_exists('multiplier', $backoff)) {
                    $multiplier = $backoff['multiplier'];

                    if ((!is_int($multiplier) && !is_float($multiplier)) || $multiplier < 1) {
                        throw InvalidConfigurationException::invalidValue('resilience.retry.backoff.multiplier', 'Must be a numeric value >= 1.');
                    }
                }
            }
        }

        if (!array_key_exists('circuit_breaker', $resilience)) {
            return;
        }

        if (!is_array($resilience['circuit_breaker'])) {
            throw InvalidConfigurationException::invalidType('resilience.circuit_breaker', 'array');
        }

        $circuitBreaker = $resilience['circuit_breaker'];

        if (array_key_exists('enabled', $circuitBreaker) && !is_bool($circuitBreaker['enabled'])) {
            throw InvalidConfigurationException::invalidType('resilience.circuit_breaker.enabled', 'bool');
        }

        foreach (['failure_threshold', 'reset_timeout_seconds', 'half_open_success_threshold'] as $key) {
            if (!array_key_exists($key, $circuitBreaker)) {
                continue;
            }

            $value = $circuitBreaker[$key];

            if (!is_int($value) || $value < 1) {
                throw InvalidConfigurationException::invalidValue("resilience.circuit_breaker.{$key}", 'Must be an integer >= 1.');
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validateMemory(array $config): void
    {
        if (!array_key_exists('memory', $config)) {
            return;
        }

        if (!is_array($config['memory'])) {
            throw InvalidConfigurationException::invalidType('memory', 'array');
        }

        if (array_key_exists('default_driver', $config['memory'])) {
            $defaultDriver = $config['memory']['default_driver'];

            if (!is_string($defaultDriver) || $defaultDriver === '') {
                throw InvalidConfigurationException::invalidValue('memory.default_driver', 'Must be a non-empty string.');
            }

            if (!in_array($defaultDriver, ['database', 'in_memory', 'redis'], true)) {
                throw InvalidConfigurationException::invalidValue('memory.default_driver', 'Must be one of: database, in_memory, redis.');
            }
        }

        if (array_key_exists('in_memory', $config['memory'])) {
            if (!is_array($config['memory']['in_memory'])) {
                throw InvalidConfigurationException::invalidType('memory.in_memory', 'array');
            }

            $inMemory = $config['memory']['in_memory'];

            if (array_key_exists('retention_days', $inMemory)) {
                $retentionDays = $inMemory['retention_days'];

                if ($retentionDays !== null && (!is_int($retentionDays) || $retentionDays < 1)) {
                    throw InvalidConfigurationException::invalidValue('memory.in_memory.retention_days', 'Must be null or an integer >= 1.');
                }
            }
        }

        if (array_key_exists('database', $config['memory'])) {
            if (!is_array($config['memory']['database'])) {
                throw InvalidConfigurationException::invalidType('memory.database', 'array');
            }

            $database = $config['memory']['database'];

            foreach (['conversations_table', 'messages_table', 'driver_name'] as $key) {
                if (!array_key_exists($key, $database)) {
                    continue;
                }

                $value = $database[$key];

                if (!is_string($value) || $value === '') {
                    throw InvalidConfigurationException::invalidValue("memory.database.{$key}", 'Must be a non-empty string.');
                }
            }

            if (array_key_exists('connection', $database)) {
                $connection = $database['connection'];

                if ($connection !== null && (!is_string($connection) || $connection === '')) {
                    throw InvalidConfigurationException::invalidType('memory.database.connection', 'string|null');
                }
            }

            if (array_key_exists('retention_days', $database)) {
                $retentionDays = $database['retention_days'];

                if ($retentionDays !== null && (!is_int($retentionDays) || $retentionDays < 1)) {
                    throw InvalidConfigurationException::invalidValue('memory.database.retention_days', 'Must be null or an integer >= 1.');
                }
            }

            if (array_key_exists('encrypt_payloads', $database) && !is_bool($database['encrypt_payloads'])) {
                throw InvalidConfigurationException::invalidType('memory.database.encrypt_payloads', 'bool');
            }
        }

        if (array_key_exists('redis', $config['memory'])) {
            if (!is_array($config['memory']['redis'])) {
                throw InvalidConfigurationException::invalidType('memory.redis', 'array');
            }

            $redis = $config['memory']['redis'];

            foreach (['prefix', 'driver_name'] as $key) {
                if (!array_key_exists($key, $redis)) {
                    continue;
                }

                $value = $redis[$key];

                if (!is_string($value) || $value === '') {
                    throw InvalidConfigurationException::invalidValue("memory.redis.{$key}", 'Must be a non-empty string.');
                }
            }

            if (array_key_exists('connection', $redis)) {
                $connection = $redis['connection'];

                if ($connection !== null && (!is_string($connection) || $connection === '')) {
                    throw InvalidConfigurationException::invalidType('memory.redis.connection', 'string|null');
                }
            }

            if (array_key_exists('retention_days', $redis)) {
                $retentionDays = $redis['retention_days'];

                if ($retentionDays !== null && (!is_int($retentionDays) || $retentionDays < 1)) {
                    throw InvalidConfigurationException::invalidValue('memory.redis.retention_days', 'Must be null or an integer >= 1.');
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validateSummarization(array $config): void
    {
        if (!array_key_exists('summarization', $config)) {
            return;
        }

        if (!is_array($config['summarization'])) {
            throw InvalidConfigurationException::invalidType('summarization', 'array');
        }

        if (array_key_exists('enabled', $config['summarization']) && !is_bool($config['summarization']['enabled'])) {
            throw InvalidConfigurationException::invalidType('summarization.enabled', 'bool');
        }

        if (array_key_exists('trigger_message_count', $config['summarization'])) {
            $triggerMessageCount = $config['summarization']['trigger_message_count'];

            if (!is_int($triggerMessageCount) || $triggerMessageCount < 1) {
                throw InvalidConfigurationException::invalidValue('summarization.trigger_message_count', 'Must be an integer >= 1.');
            }
        }
    }
}
