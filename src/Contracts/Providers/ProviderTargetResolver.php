<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Providers;

use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ResolvedProviderTarget;

interface ProviderTargetResolver
{
    /**
     * Resolve a text-runtime provider target.
     *
     * A null or empty name selects the configured Agent Kit default profile.
     * A registered profile name is resolved even when it is absent from
     * failover_order. An unregistered name is treated as a direct Laravel AI
     * provider instance name for backwards compatibility.
     */
    public function resolve(?string $requestedName, ?string $requestModel = null): ResolvedProviderTarget;

    /**
     * Resolve a modality-runtime provider target.
     *
     * A null or empty name leaves the Laravel AI provider instance unset so the
     * SDK default applies. Registered profiles and direct SDK names follow the
     * same rules as {@see resolve()}.
     */
    public function resolveExplicit(?string $requestedName, ?string $requestModel = null): ResolvedProviderTarget;

    public function fromDefinition(ProviderDefinition $definition, ?string $requestModel = null): ResolvedProviderTarget;

    /**
     * Names that mark a `providerOptions` map as scoped to specific providers.
     *
     * Includes Agent Kit profile names, drivers, `sdk_provider` values, and
     * Laravel AI provider instance names from `config/ai.php`.
     *
     * @return list<string>
     */
    public function knownProviderScopeKeys(): array;
}
