<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality;

/**
 * @param list<list<float>> $vectors
 * @param array<string, mixed> $metadata
 */
final readonly class EmbeddingsResult
{
    /**
     * @param list<list<float>> $vectors
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $runId,
        public array $vectors,
        public int $tokenCount,
        public string $provider,
        public string $model,
        public array $metadata = [],
    ) {
    }
}
