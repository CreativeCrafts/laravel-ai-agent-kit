<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Tools;

interface ToolAuthorizer
{
    /**
     * Decide whether a custom tool (local Tool contract) may be invoked.
     *
     * @param array<string, mixed> $input
     */
    public function authorizeCustomTool(Tool $tool, array $input): bool;

    /**
     * Decide whether a provider-native tool (SDK WebSearch/WebFetch/FileSearch/…)
     * may be materialized and passed to the provider for this call.
     *
     * Provider tools execute server-side on the model provider, not locally,
     * so authorizers typically gate by name alone — they are billable,
     * rate-limited, and leak data to the provider regardless.
     */
    public function authorizeProviderTool(string $providerToolName): bool;
}
