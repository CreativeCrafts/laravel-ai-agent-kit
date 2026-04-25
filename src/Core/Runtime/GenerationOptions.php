<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use InvalidArgumentException;

final readonly class GenerationOptions
{
    /**
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
     * Merge non-null typed fields with providerOptions into a single map.
     *
     * Entries from providerOptions take precedence over typed fields on key
     * collision, matching the SDK driver's own array_merge semantics and
     * giving callers an explicit override path.
     *
     * @return array<string, mixed>
     */
    public function toProviderOptionsMap(): array
    {
        $typed = [];

        if ($this->temperature !== null) {
            $typed['temperature'] = $this->temperature;
        }

        if ($this->maxTokens !== null) {
            $typed['maxTokens'] = $this->maxTokens;
        }

        if ($this->maxSteps !== null) {
            $typed['maxSteps'] = $this->maxSteps;
        }

        return array_merge($typed, $this->providerOptions);
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
}
