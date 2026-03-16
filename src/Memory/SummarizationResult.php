<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Memory;

final readonly class SummarizationResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public ?string $summary,
        public bool $shouldPersist,
        public int $summarizedMessageCount,
        public array $metadata = [],
    ) {
    }

    public function hasSummary(): bool
    {
        return $this->summary !== null && $this->summary !== '';
    }

    public function metadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
}
