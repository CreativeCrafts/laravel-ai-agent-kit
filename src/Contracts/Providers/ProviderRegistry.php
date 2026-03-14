<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Providers;

use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderDefinition;

interface ProviderRegistry
{
    /**
     * @return array<string, ProviderDefinition>
     */
    public function all(): array;

    public function has(string $providerName): bool;

    public function get(string $providerName): ProviderDefinition;
}
