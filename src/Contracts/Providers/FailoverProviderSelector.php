<?php

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Providers;

use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderDefinition;

interface FailoverProviderSelector
{
    /**
     * @return list<ProviderDefinition>
     */
    public function ordered(): array;

    public function nextAfter(string $currentProviderName): ?ProviderDefinition;
}
