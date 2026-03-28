<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Providers;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\AgentProviderProfileSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\NoCompatibleAgentProviderProfileException;

final readonly class ConfiguredAgentProviderProfileSelector implements AgentProviderProfileSelector
{
    public function __construct(
        private ProviderRegistry $providerRegistry,
    ) {
    }

    public function selectForAgent(AgentDefinition $agentDefinition): ProviderDefinition
    {
        $attempts = [];

        foreach ($agentDefinition->providerProfiles() as $providerProfile) {
            if (!$this->providerRegistry->has($providerProfile)) {
                $attempts[] = sprintf('profile [%s] is not defined', $providerProfile);

                continue;
            }

            $definition = $this->providerRegistry->get($providerProfile);

            if (!$definition->enabled) {
                $attempts[] = sprintf('profile [%s] is disabled', $providerProfile);

                continue;
            }

            $missingCapabilities = $definition->missingCapabilities($agentDefinition->requiredCapabilities);

            if ($missingCapabilities !== []) {
                $attempts[] = sprintf(
                    'profile [%s] is missing capabilities [%s]',
                    $providerProfile,
                    implode(', ', $missingCapabilities),
                );

                continue;
            }

            return $definition;
        }

        throw NoCompatibleAgentProviderProfileException::forAgent(
            agentKey: $agentDefinition->key,
            attempts: $attempts,
        );
    }
}
