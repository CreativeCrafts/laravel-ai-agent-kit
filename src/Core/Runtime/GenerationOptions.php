<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use InvalidArgumentException;

final readonly class GenerationOptions
{
    /**
     * Raw provider-native options.
     *
     * Prefer maps keyed by Laravel AI provider instance name or driver:
     *
     *     ['openai' => ['reasoning' => ['effort' => 'medium']]]
     *
     * Unscoped maps (no provider/driver keys) are preserved for backwards
     * compatibility and apply to every attempt. Scoped maps are isolated per
     * attempt so OpenAI-native options are not forwarded to Anthropic.
     *
     * Typed fields (`temperature`, `maxTokens`, `maxSteps`) are never mixed
     * into this map. Laravel AI translates those through agent methods.
     *
     * @param array<string, mixed> $providerOptions
     */
    public function __construct(
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        public ?int $maxSteps = null,
        public array $providerOptions = [],
    ) {
        if ($this->temperature !== null && ($this->temperature < 0.0 || $this->temperature > 2.0)) {
            throw new InvalidArgumentException(
                sprintf('Generation option [temperature] must be within [0.0, 2.0] when provided, got [%f].', $this->temperature),
            );
        }

        if ($this->maxTokens !== null && $this->maxTokens < 1) {
            throw new InvalidArgumentException(
                sprintf('Generation option [maxTokens] must be >= 1 when provided, got [%d].', $this->maxTokens),
            );
        }

        if ($this->maxSteps !== null && $this->maxSteps < 1) {
            throw new InvalidArgumentException(
                sprintf('Generation option [maxSteps] must be >= 1 when provided, got [%d].', $this->maxSteps),
            );
        }

        $this->validateProviderOptionKeys($this->providerOptions);
    }

    /**
     * Raw provider-native options only. Typed generation controls are not included.
     *
     * @return array<string, mixed>
     */
    public function toProviderOptionsMap(): array
    {
        return $this->providerOptions;
    }

    /**
     * Resolve request-level provider-native options for one attempt.
     *
     * Lookup order:
     * 1. nested map keyed by the Laravel AI provider instance name
     * 2. nested map keyed by the Agent Kit / Laravel AI driver
     * 3. empty array when the map is scoped to other providers
     * 4. the whole map when it is unscoped (legacy)
     *
     * @param list<string> $additionalScopeKeys
     * @return array<string, mixed>
     */
    public function providerOptionsFor(string $sdkProviderName, string $driver, array $additionalScopeKeys = []): array
    {
        foreach ([$sdkProviderName, $driver] as $key) {
            if ($key === '') {
                continue;
            }

            $nested = $this->providerOptions[$key] ?? null;

            if (is_array($nested)) {
                return $this->stringKeyedMap($nested);
            }
        }

        if ($this->isScopedProviderOptionsMap($additionalScopeKeys)) {
            return [];
        }

        return $this->providerOptions;
    }

    /**
     * Build attempt-local options: typed fields are preserved, raw options are
     * the profile defaults merged with request overrides for this provider.
     *
     * @param array<string, mixed> $profileOptions
     * @param list<string> $additionalScopeKeys
     */
    public function forProviderAttempt(
        string $sdkProviderName,
        string $driver,
        array $profileOptions = [],
        array $additionalScopeKeys = [],
    ): self {
        $requestOptions = $this->providerOptionsFor($sdkProviderName, $driver, $additionalScopeKeys);

        return new self(
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            maxSteps: $this->maxSteps,
            providerOptions: array_merge($profileOptions, $requestOptions),
        );
    }

    /**
     * @param array<int|string, mixed> $providerOptions
     */
    private function validateProviderOptionKeys(array $providerOptions): void
    {
        foreach (array_keys($providerOptions) as $key) {
            if (!is_string($key) || $key === '') {
                throw new InvalidArgumentException('Generation option [providerOptions] keys must be non-empty strings.');
            }
        }
    }

    /**
     * @param list<string> $additionalScopeKeys
     */
    private function isScopedProviderOptionsMap(array $additionalScopeKeys): bool
    {
        $scopeKeys = $this->scopeKeys($additionalScopeKeys);

        foreach (array_keys($this->providerOptions) as $key) {
            if (in_array($key, $scopeKeys, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $additionalScopeKeys
     * @return list<string>
     */
    private function scopeKeys(array $additionalScopeKeys): array
    {
        $keys = [];

        foreach ($additionalScopeKeys as $key) {
            if ($key === '' || in_array($key, $keys, true)) {
                continue;
            }

            $keys[] = $key;
        }

        return $keys;
    }

    /**
     * @param array<int|string, mixed> $map
     * @return array<string, mixed>
     */
    private function stringKeyedMap(array $map): array
    {
        $normalized = [];

        foreach ($map as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
