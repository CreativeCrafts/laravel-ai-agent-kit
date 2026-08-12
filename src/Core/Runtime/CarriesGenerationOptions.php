<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use Laravel\Ai\Enums\Lab;

/**
 * Exposes Agent Kit generation controls through Laravel AI's typed agent
 * methods and the separate HasProviderOptions raw channel.
 *
 * Typed fields (temperature, maxTokens, maxSteps) must not be placed in
 * providerOptions(); Laravel AI translates those itself.
 */
trait CarriesGenerationOptions
{
    public readonly ?GenerationOptions $generationOptions;

    public function maxTokens(): ?int
    {
        return $this->generationOptions?->maxTokens;
    }

    public function maxSteps(): ?int
    {
        return $this->generationOptions?->maxSteps;
    }

    public function temperature(): ?float
    {
        return $this->generationOptions?->temperature;
    }

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        if (!$this->generationOptions instanceof GenerationOptions) {
            return [];
        }

        return $this->generationOptions->providerOptions;
    }
}
