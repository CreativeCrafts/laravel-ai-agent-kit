<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Vector;

final readonly class VectorSearchResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $id,
        public float $score,
        public array $metadata = [],
    ) {
    }
}
