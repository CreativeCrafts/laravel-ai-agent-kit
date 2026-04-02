<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Providers;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\FailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience\CircuitBreakerManager;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderDisabledException;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderNotInFailoverOrderException;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\ProviderFailoverResolved;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\ProviderSkippedByCircuitBreaker;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class ConfiguredFailoverProviderSelector implements FailoverProviderSelector
{
    public function __construct(
        private ConfigRepository $config,
        private ProviderRegistry $providerRegistry,
        private string $failoverOrderConfigKey = 'ai-agent-kit.failover_order',
        private ?Dispatcher $events = null,
        private ?CircuitBreakerManager $circuitBreakerManager = null,
        private string $circuitBreakerFailoverConfigKey = 'ai-agent-kit.resilience.circuit_breaker.apply_to_failover',
    ) {
    }

    public function nextAfter(string $currentProviderName): ?ProviderDefinition
    {
        $orderedProviders = $this->enabledProvidersInFailoverOrder();
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

        $nextProvider = null;

        foreach (array_slice($orderedProviders, $currentIndex + 1) as $candidate) {
            if ($this->isSkippedByCircuitBreaker($candidate, true)) {
                continue;
            }

            $nextProvider = $candidate;

            break;
        }

        $this->dispatch(
            ProviderFailoverResolved::fromDefinitions(
                $currentProviderName,
                $nextProvider,
                $this->filterByCircuitBreaker($orderedProviders, false),
            ),
        );

        return $nextProvider;
    }

    /**
     * @return list<ProviderDefinition>
     */
    public function ordered(): array
    {
        return $this->filterByCircuitBreaker($this->enabledProvidersInFailoverOrder(), true);
    }

    /**
     * @return list<ProviderDefinition>
     */
    private function enabledProvidersInFailoverOrder(): array
    {
        $failoverOrder = $this->config->get($this->failoverOrderConfigKey, []);

        if (!is_array($failoverOrder)) {
            return [];
        }

        $providers = [];

        foreach ($failoverOrder as $providerName) {
            if (!is_string($providerName)) {
                continue;
            }
            if ($providerName === '') {
                continue;
            }
            $provider = $this->providerRegistry->get($providerName);

            if (!$provider->enabled) {
                throw ProviderDisabledException::named($providerName);
            }

            $providers[] = $provider;
        }

        return $providers;
    }

    private function isSkippedByCircuitBreaker(ProviderDefinition $provider, bool $emitSkipEvents): bool
    {
        if (!$this->isCircuitBreakerAppliedToFailover()) {
            return false;
        }

        if (!$this->circuitBreakerManager instanceof CircuitBreakerManager) {
            return false;
        }

        $breaker = $this->circuitBreakerManager->for('providers.' . $provider->name);

        if ($breaker->allowsExecution()) {
            return false;
        }

        if ($emitSkipEvents) {
            $snapshot = $breaker->snapshot();

            $this->dispatch(
                new ProviderSkippedByCircuitBreaker(
                    provider: $provider->name,
                    state: $snapshot->state->value,
                    consecutiveFailures: $snapshot->consecutiveFailures,
                ),
            );
        }

        return true;
    }

    private function isCircuitBreakerAppliedToFailover(): bool
    {
        return (bool)$this->config->get($this->circuitBreakerFailoverConfigKey, false);
    }

    private function dispatch(object $event): void
    {
        if ($this->events instanceof Dispatcher) {
            $this->events->dispatch($event);
        }
    }

    /**
     * @param list<ProviderDefinition> $providers
     * @return list<ProviderDefinition>
     */
    private function filterByCircuitBreaker(array $providers, bool $emitSkipEvents): array
    {
        $eligibleProviders = [];

        foreach ($providers as $provider) {
            if ($this->isSkippedByCircuitBreaker($provider, $emitSkipEvents)) {
                continue;
            }

            $eligibleProviders[] = $provider;
        }

        return $eligibleProviders;
    }
}
