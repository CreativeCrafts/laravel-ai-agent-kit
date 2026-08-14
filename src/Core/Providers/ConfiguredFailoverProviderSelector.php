<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Providers;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\CapabilityAwareFailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience\CircuitBreakerManager;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderDisabledException;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderNotInFailoverOrderException;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\ProviderFailoverExhausted;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\ProviderFailoverResolved;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\ProviderSkippedByCircuitBreaker;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\ProviderSkippedByCapabilities;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class ConfiguredFailoverProviderSelector implements CapabilityAwareFailoverProviderSelector
{
    private AuditedProviderCapabilityMatrix $capabilityMatrix;

    public function __construct(
        private ConfigRepository $config,
        private ProviderRegistry $providerRegistry,
        private string $failoverOrderConfigKey = 'ai-agent-kit.failover_order',
        private ?Dispatcher $events = null,
        private ?CircuitBreakerManager $circuitBreakerManager = null,
        private string $circuitBreakerFailoverConfigKey = 'ai-agent-kit.resilience.circuit_breaker.apply_to_failover',
        ?AuditedProviderCapabilityMatrix $capabilityMatrix = null,
    ) {
        $this->capabilityMatrix = $capabilityMatrix ?? new AuditedProviderCapabilityMatrix();
    }

    public function nextAfter(string $currentProviderName): ?ProviderDefinition
    {
        return $this->nextAfterSupporting($currentProviderName, []);
    }

    public function nextAfterSupporting(
        string $currentProviderName,
        array $requiredCapabilities,
    ): ?ProviderDefinition {
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

            if ($this->isSkippedByCapabilities($candidate, $requiredCapabilities, true)) {
                continue;
            }

            $nextProvider = $candidate;

            break;
        }

        $eligibleProviders = $this->filterEligible($orderedProviders, $requiredCapabilities, false);

        if (!$nextProvider instanceof ProviderDefinition) {
            $this->dispatch(
                ProviderFailoverExhausted::fromDefinitions(
                    $currentProviderName,
                    $eligibleProviders,
                ),
            );
        }

        $this->dispatch(
            ProviderFailoverResolved::fromDefinitions(
                $currentProviderName,
                $nextProvider,
                $eligibleProviders,
            ),
        );

        return $nextProvider;
    }

    /**
     * @return list<ProviderDefinition>
     */
    public function ordered(): array
    {
        return $this->orderedSupporting([]);
    }

    public function orderedSupporting(array $requiredCapabilities): array
    {
        return $this->filterEligible($this->enabledProvidersInFailoverOrder(), $requiredCapabilities, true);
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

    /**
     * @param list<string> $requiredCapabilities
     */
    private function isSkippedByCapabilities(
        ProviderDefinition $provider,
        array $requiredCapabilities,
        bool $emitSkipEvents,
    ): bool {
        $missingCapabilities = $this->capabilityMatrix->missingDeclaredCapabilities(
            $provider,
            $requiredCapabilities,
        );

        if ($missingCapabilities === []) {
            return false;
        }

        if ($emitSkipEvents) {
            $this->dispatch(new ProviderSkippedByCapabilities(
                provider: $provider->name,
                providerSkippedReason: 'missing_capabilities',
                missingCapabilities: $missingCapabilities,
            ));
        }

        return true;
    }

    private function dispatch(object $event): void
    {
        if ($this->events instanceof Dispatcher) {
            $this->events->dispatch($event);
        }
    }

    /**
     * @param list<ProviderDefinition> $providers
     * @param list<string> $requiredCapabilities
     * @return list<ProviderDefinition>
     */
    private function filterEligible(
        array $providers,
        array $requiredCapabilities,
        bool $emitSkipEvents,
    ): array {
        $eligibleProviders = [];

        foreach ($providers as $provider) {
            if ($this->isSkippedByCircuitBreaker($provider, $emitSkipEvents)) {
                continue;
            }

            if ($this->isSkippedByCapabilities($provider, $requiredCapabilities, $emitSkipEvents)) {
                continue;
            }

            $eligibleProviders[] = $provider;
        }

        return $eligibleProviders;
    }
}
