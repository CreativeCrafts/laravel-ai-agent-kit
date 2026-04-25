<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tools;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\Tool;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolAuthorizer;

/**
 * Convenience base class for consumers that do not distinguish between custom
 * and provider tool families at the policy level.
 *
 * Override the single `authorize()` method and both family-specific methods
 * will route through it. Override either `authorizeCustomTool()` or
 * `authorizeProviderTool()` individually to specialize one family.
 */
abstract class AbstractToolAuthorizer implements ToolAuthorizer
{
    /**
     * Single-method override point for consumers that treat both tool families
     * with the same policy.
     *
     * - For custom tools, `$tool` is the resolved Tool instance and `$input`
     *   is the caller-provided input payload.
     * - For provider tools, `$tool` is `null` and `$input` is `[]`.
     *
     * @param array<string, mixed> $input
     */
    abstract protected function authorize(ToolKind $kind, string $name, ?Tool $tool, array $input): bool;

    /**
     * @param array<string, mixed> $input
     */
    public function authorizeCustomTool(Tool $tool, array $input): bool
    {
        return $this->authorize(ToolKind::Custom, $tool->name(), $tool, $input);
    }

    public function authorizeProviderTool(string $providerToolName): bool
    {
        return $this->authorize(ToolKind::Provider, $providerToolName, null, []);
    }
}
