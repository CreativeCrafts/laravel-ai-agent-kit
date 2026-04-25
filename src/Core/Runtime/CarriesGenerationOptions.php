<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use Laravel\Ai\Enums\Lab;

/**
 * Implements Laravel\Ai\Contracts\HasProviderOptions by delegating to a
 * stored GenerationOptions instance. Applied to kit telemetry agents so
 * runtime-configurable knobs (temperature, maxTokens, maxSteps, provider
 * specifics) reach the SDK's provider drivers via the providerOptions
 * channel — the only runtime-accessible configuration path the SDK exposes.
 */
trait CarriesGenerationOptions
{
    public readonly ?GenerationOptions $generationOptions;

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        if (!$this->generationOptions instanceof GenerationOptions) {
            return [];
        }

        return $this->generationOptions->toProviderOptionsMap();
    }
}
