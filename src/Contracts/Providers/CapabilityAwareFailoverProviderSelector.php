<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Providers;

use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderDefinition;

interface CapabilityAwareFailoverProviderSelector extends FailoverProviderSelector
{
    /**
     * @param list<string> $requiredCapabilities
     * @return list<ProviderDefinition>
     */
    public function orderedSupporting(array $requiredCapabilities): array;

    /**
     * @param list<string> $requiredCapabilities
     */
    public function nextAfterSupporting(
        string $currentProviderName,
        array $requiredCapabilities,
    ): ?ProviderDefinition;
}
