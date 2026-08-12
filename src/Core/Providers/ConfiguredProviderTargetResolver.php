<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Providers;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderTargetResolver;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderDisabledException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final readonly class ConfiguredProviderTargetResolver implements ProviderTargetResolver
{
    public function __construct(
        private ProviderRegistry $providerRegistry,
        private ProviderSelector $providerSelector,
        private ConfigRepository $config,
    ) {
    }

    public function resolve(?string $requestedName, ?string $requestModel = null): ResolvedProviderTarget
    {
        if ($requestedName === null || $requestedName === '') {
            return $this->fromDefinition($this->providerSelector->selectDefault(), $requestModel);
        }

        return $this->resolveNamed($requestedName, $requestModel);
    }

    public function resolveExplicit(?string $requestedName, ?string $requestModel = null): ResolvedProviderTarget
    {
        if ($requestedName === null || $requestedName === '') {
            return new ResolvedProviderTarget(
                profileName: null,
                sdkProviderName: null,
                driver: null,
                model: $requestModel,
                providerOptions: [],
            );
        }

        return $this->resolveNamed($requestedName, $requestModel);
    }

    public function fromDefinition(ProviderDefinition $definition, ?string $requestModel = null): ResolvedProviderTarget
    {
        $profileModel = $definition->model();

        return new ResolvedProviderTarget(
            profileName: $definition->name,
            sdkProviderName: $definition->sdkProviderName(),
            driver: $definition->driver,
            model: $requestModel ?? $profileModel,
            providerOptions: $definition->providerOptions(),
        );
    }

    /**
     * @return list<string>
     */
    public function knownProviderScopeKeys(): array
    {
        $keys = [];

        foreach ($this->providerRegistry->all() as $definition) {
            $keys[] = $definition->name;
            $keys[] = $definition->driver;
            $keys[] = $definition->sdkProviderName();
        }

        $laravelAiProviders = $this->config->get('ai.providers', []);

        if (is_array($laravelAiProviders)) {
            foreach (array_keys($laravelAiProviders) as $name) {
                if (!is_string($name)) {
                    continue;
                }

                $keys[] = $name;
            }
        }

        $unique = [];

        foreach ($keys as $key) {
            if ($key === '' || in_array($key, $unique, true)) {
                continue;
            }

            $unique[] = $key;
        }

        return $unique;
    }

    private function resolveNamed(string $requestedName, ?string $requestModel): ResolvedProviderTarget
    {
        if ($this->providerRegistry->has($requestedName)) {
            $definition = $this->providerRegistry->get($requestedName);

            if (!$definition->enabled) {
                throw ProviderDisabledException::named($requestedName);
            }

            return $this->fromDefinition($definition, $requestModel);
        }

        return new ResolvedProviderTarget(
            profileName: null,
            sdkProviderName: $requestedName,
            driver: $requestedName,
            model: $requestModel,
            providerOptions: [],
        );
    }
}
