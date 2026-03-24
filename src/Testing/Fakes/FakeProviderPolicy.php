<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Testing\Fakes;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\FailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderDisabledException;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderNotDefinedException;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderNotInFailoverOrderException;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderDefinition;
use LogicException;

final class FakeProviderPolicy implements FailoverProviderSelector, ProviderRegistry, ProviderSelector
{
    /**
     * @var array<string, ProviderDefinition>
     */
    private array $providers = [];

    /**
     * @var list<string>
     */
    private array $failoverOrder = [];

    /**
     * @var list<string>
     */
    private array $selectedDefaults = [];

    /**
     * @var list<string>
     */
    private array $requestedProviders = [];

    /**
     * @var list<string>
     */
    private array $failoverLookups = [];

    /**
     * @param iterable<ProviderDefinition> $providers
     * @param list<string>|null $failoverOrder
     */
    public function __construct(
        iterable $providers = [],
        private ?string $defaultProviderName = null,
        ?array $failoverOrder = null,
    ) {
        foreach ($providers as $provider) {
            $this->register($provider);
        }

        $this->failoverOrder = $failoverOrder ?? array_keys($this->providers);
    }

    public function register(ProviderDefinition $provider): self
    {
        $this->providers[$provider->name] = $provider;

        if (!in_array($provider->name, $this->failoverOrder, true)) {
            $this->failoverOrder[] = $provider->name;
        }

        if ($this->defaultProviderName === null) {
            $this->defaultProviderName = $provider->name;
        }

        return $this;
    }

    public function setDefault(string $providerName): self
    {
        $this->defaultProviderName = $providerName;

        return $this;
    }

    /**
     * @param list<string> $providerNames
     */
    public function setFailoverOrder(array $providerNames): self
    {
        $this->failoverOrder = array_values(
            array_filter(
                $providerNames,
                static fn (string $providerName): bool => $providerName !== '',
            ),
        );

        return $this;
    }

    /**
     * @return array<string, ProviderDefinition>
     */
    public function all(): array
    {
        return $this->providers;
    }

    public function has(string $providerName): bool
    {
        return array_key_exists($providerName, $this->providers);
    }

    public function selectDefault(): ProviderDefinition
    {
        $providerName = $this->defaultProviderName;

        if ($providerName === null || $providerName === '') {
            throw new LogicException('Default provider configuration is missing or invalid.');
        }

        $provider = $this->get($providerName);

        if (!$provider->enabled) {
            throw ProviderDisabledException::named($providerName);
        }

        $this->selectedDefaults[] = $providerName;

        return $provider;
    }

    public function get(string $providerName): ProviderDefinition
    {
        $this->requestedProviders[] = $providerName;

        return $this->providers[$providerName] ?? throw ProviderNotDefinedException::named($providerName);
    }

    public function nextAfter(string $currentProviderName): ?ProviderDefinition
    {
        $this->failoverLookups[] = $currentProviderName;

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
        $providers = [];

        foreach ($this->failoverOrder as $providerName) {
            $provider = $this->get($providerName);

            if (!$provider->enabled) {
                throw ProviderDisabledException::named($providerName);
            }

            $providers[] = $provider;
        }

        return $providers;
    }

    /**
     * @return list<string>
     */
    public function selectedDefaults(): array
    {
        return $this->selectedDefaults;
    }

    /**
     * @return list<string>
     */
    public function requestedProviders(): array
    {
        return $this->requestedProviders;
    }

    /**
     * @return list<string>
     */
    public function failoverLookups(): array
    {
        return $this->failoverLookups;
    }
}
