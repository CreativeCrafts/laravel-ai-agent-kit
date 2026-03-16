<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Config;

use CreativeCrafts\LaravelAiAgentKit\Core\Config\Exceptions\InvalidConfigurationException;
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
        $this->validateMemory($config);
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

            if (!in_array($defaultDriver, ['database', 'in_memory'], true)) {
                throw InvalidConfigurationException::invalidValue('memory.default_driver', 'Must be one of: database, in_memory.');
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

        if (!array_key_exists('database', $config['memory'])) {
            return;
        }

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
}
