<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Providers;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderNotDefinedException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class ConfiguredProviderRegistry implements ProviderRegistry
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly string $configKey = 'ai-agent-kit.providers',
    ) {
    }

    public function has(string $providerName): bool
    {
        return array_key_exists($providerName, $this->all());
    }

    /**
     * @return array<string, ProviderDefinition>
     */
    public function all(): array
    {
        $providers = $this->config->get($this->configKey, []);

        if (!is_array($providers)) {
            return [];
        }

        $definitions = [];

        foreach ($providers as $name => $provider) {
            if (!is_string($name)) {
                continue;
            }

            if (!is_array($provider)) {
                continue;
            }

            $driver = $provider['driver'] ?? null;

            if (!is_string($driver)) {
                continue;
            }

            if ($driver === '') {
                continue;
            }

            $enabled = $provider['enabled'] ?? true;
            $options = $provider['options'] ?? [];
            $capabilities = $provider['capabilities'] ?? [];
            $sdkProvider = $provider['sdk_provider'] ?? null;

            $normalizedOptions = [];

            if (is_array($options)) {
                foreach ($options as $optionKey => $optionValue) {
                    if (is_string($optionKey)) {
                        $normalizedOptions[$optionKey] = $optionValue;
                    }
                }
            }

            $normalizedCapabilities = [];

            if (is_array($capabilities)) {
                foreach ($capabilities as $capability) {
                    if (is_string($capability) && $capability !== '' && !in_array($capability, $normalizedCapabilities, true)) {
                        $normalizedCapabilities[] = $capability;
                    }
                }
            }

            $definitions[$name] = new ProviderDefinition(
                name: $name,
                driver: $driver,
                enabled: is_bool($enabled) && $enabled,
                options: $normalizedOptions,
                capabilities: $normalizedCapabilities,
                sdkProvider: is_string($sdkProvider) && $sdkProvider !== '' ? $sdkProvider : null,
            );
        }

        return $definitions;
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
