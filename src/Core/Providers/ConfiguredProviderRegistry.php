<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Providers;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderNotDefinedException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final readonly class ConfiguredProviderRegistry implements ProviderRegistry
{
    public function __construct(
        private ConfigRepository $config,
        private string $configKey = 'ai-agent-kit.providers',
    ) {
    }

    /**
     * @return array<string, ProviderDefinition>
     */
    public function all(): array
    {
        $providers = $this->config->get($this->configKey, []);

        if (! is_array($providers)) {
            return [];
        }

        $definitions = [];

        foreach ($providers as $name => $provider) {
            if (! is_string($name)) {
                continue;
            }
            if (! is_array($provider)) {
                continue;
            }
            $driver = $provider['driver'] ?? null;
            if (! is_string($driver)) {
                continue;
            }
            if ($driver === '') {
                continue;
            }

            $enabled = $provider['enabled'] ?? true;
            $options = $provider['options'] ?? [];

            $normalizedOptions = [];

            if (is_array($options)) {
                foreach ($options as $optionKey => $optionValue) {
                    if (is_string($optionKey)) {
                        $normalizedOptions[$optionKey] = $optionValue;
                    }
                }
            }

            $definitions[$name] = new ProviderDefinition(
                name: $name,
                driver: $driver,
                enabled: is_bool($enabled) && $enabled,
                options: $normalizedOptions,
            );
        }

        return $definitions;
    }

    public function has(string $providerName): bool
    {
        return array_key_exists($providerName, $this->all());
    }

    public function get(string $providerName): ProviderDefinition
    {
        $provider = $this->all()[$providerName] ?? null;

        if ($provider === null) {
            throw ProviderNotDefinedException::named($providerName);
        }

        return $provider;
    }
}
