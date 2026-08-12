<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Providers;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderTargetResolver;

final readonly class ConfiguredProviderTargetResolver implements ProviderTargetResolver
{
    public function __construct(
        private ProviderRegistry $providerRegistry,
        private ProviderSelector $providerSelector,
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
            return $this->fromDefinition($this->providerRegistry->get($requestedName), $requestModel);
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
