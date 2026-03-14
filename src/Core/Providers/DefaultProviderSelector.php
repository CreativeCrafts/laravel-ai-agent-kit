<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Providers;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderDisabledException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use LogicException;

final readonly class DefaultProviderSelector implements ProviderSelector
{
    public function __construct(
        private ConfigRepository $config,
        private ProviderRegistry $providerRegistry,
        private string $defaultProviderConfigKey = 'ai-agent-kit.default_provider',
    ) {
    }

    public function selectDefault(): ProviderDefinition
    {
        $providerName = $this->config->get($this->defaultProviderConfigKey);

        if (! is_string($providerName) || $providerName === '') {
            throw new LogicException('Default provider configuration is missing or invalid.');
        }

        $provider = $this->providerRegistry->get($providerName);

        if (! $provider->enabled) {
            throw ProviderDisabledException::named($providerName);
        }

        return $provider;
    }
}
