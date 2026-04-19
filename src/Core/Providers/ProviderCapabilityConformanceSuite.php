<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Providers;

use Closure;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderCapabilityConformanceException;
use Throwable;

final readonly class ProviderCapabilityConformanceSuite
{
    public function __construct(
        private ProviderRegistry $providerRegistry,
        private AuditedProviderCapabilityMatrix $capabilityMatrix,
    ) {
    }

    /**
     * @param Closure(ProviderDefinition): void $probe
     * @throws ProviderCapabilityConformanceException
     */
    public function assertProfileConforms(
        string $providerProfile,
        string $capability,
        Closure $probe,
    ): void {
        if (!$this->capabilityMatrix->has($capability)) {
            throw ProviderCapabilityConformanceException::forUnknownCapability($capability);
        }

        $providerDefinition = $this->providerRegistry->get($providerProfile);
        $missingCapabilities = $this->capabilityMatrix->missingProfileRequirements(
            $providerDefinition,
            $capability,
        );

        if ($missingCapabilities !== []) {
            throw ProviderCapabilityConformanceException::forProfileMismatch(
                capability: $capability,
                providerProfile: $providerProfile,
                missingCapabilities: $missingCapabilities,
            );
        }

        try {
            $probe($providerDefinition);
        } catch (ProviderCapabilityConformanceException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw ProviderCapabilityConformanceException::forProfileProbeFailure(
                capability: $capability,
                providerProfile: $providerProfile,
                previous: $throwable,
            );
        }
    }

    /**
     * @param array<string, string> $profilesByStage
     * @param Closure(array<string, ProviderDefinition>): void $probe
     * @throws ProviderCapabilityConformanceException
     */
    public function assertStagesConform(
        string $capability,
        array $profilesByStage,
        Closure $probe,
    ): void {
        if (!$this->capabilityMatrix->has($capability)) {
            throw ProviderCapabilityConformanceException::forUnknownCapability($capability);
        }

        $providerDefinitionsByStage = [];

        foreach ($profilesByStage as $stage => $providerProfile) {
            $providerDefinitionsByStage[$stage] = $this->providerRegistry->get($providerProfile);
        }

        $missingCapabilitiesByStage = $this->capabilityMatrix->missingStageRequirements(
            $providerDefinitionsByStage,
            $capability,
        );

        if ($missingCapabilitiesByStage !== []) {
            throw ProviderCapabilityConformanceException::forStageMismatch(
                capability: $capability,
                profilesByStage: $profilesByStage,
                missingCapabilitiesByStage: $missingCapabilitiesByStage,
            );
        }

        try {
            $probe($providerDefinitionsByStage);
        } catch (ProviderCapabilityConformanceException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw ProviderCapabilityConformanceException::forStageProbeFailure(
                capability: $capability,
                profilesByStage: $profilesByStage,
                previous: $throwable,
            );
        }
    }

    /**
     * @return list<string>
     */
    public function conformedCapabilitiesForProfile(string $providerProfile): array
    {
        return $this->capabilityMatrix->conformedCapabilitiesForProfile(
            $this->providerRegistry->get($providerProfile),
        );
    }
}
