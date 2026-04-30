<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality;

use InvalidArgumentException;

/**
 * @param array<string, mixed> $metadata
 */
final readonly class RerankingRequest
{
    /**
     * @param array<int, string> $documents
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $runId,
        public array $documents,
        public string $query,
        public ?int $limit = null,
        public ?string $provider = null,
        public ?string $model = null,
        public array $metadata = [],
    ) {
        if ($this->runId === '') {
            throw new InvalidArgumentException('Reranking requests require a non-empty runId.');
        }

        if ($this->documents === []) {
            throw new InvalidArgumentException('Reranking requests require at least one document.');
        }

        if (!array_is_list($this->documents)) {
            throw new InvalidArgumentException('Reranking documents must be a list.');
        }

        if ($this->query === '') {
            throw new InvalidArgumentException('Reranking requests require a non-empty query.');
        }
    }
}
