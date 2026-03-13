<?php

namespace CreativeCrafts\LaravelAiAgentKit\Core\Providers;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\FailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderDisabledException;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderNotInFailoverOrderException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final readonly class ConfiguredFailoverProviderSelector implements FailoverProviderSelector
{
    public function __construct(
        private ConfigRepository $config,
        private ProviderRegistry $providerRegistry,
        private string $failoverOrderConfigKey = 'ai-agent-kit.failover_order',
    ) {}

    public function nextAfter(string $currentProviderName): ?ProviderDefinition
    {
        $orderedProviders = $this->ordered();
        $currentIndex = null;

        foreach ($orderedProviders as $index => $provider) {
            if ($provider->name === $currentProviderName) {
                $currentIndex = $index;

                break;
            }
        }

        if ($currentIndex === null) {
            throw ProviderNotInFailoverOrderException::named($currentProviderName);
        }

        return $orderedProviders[$currentIndex + 1] ?? null;
    }

    /**
     * @return list<ProviderDefinition>
     */
    public function ordered(): array
    {
        $failoverOrder = $this->config->get($this->failoverOrderConfigKey, []);

        if (! is_array($failoverOrder)) {
            return [];
        }

        $providers = [];

        foreach ($failoverOrder as $providerName) {
            if (! is_string($providerName)) {
                continue;
            }
            if ($providerName === '') {
                continue;
            }
            $provider = $this->providerRegistry->get($providerName);

            if (! $provider->enabled) {
                throw ProviderDisabledException::named($providerName);
            }

            $providers[] = $provider;
        }

        return $providers;
    }
}
