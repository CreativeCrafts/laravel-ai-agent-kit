<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality;

/**
 * @param list<RerankedDocumentResult> $documents
 * @param array<string, mixed> $metadata
 */
final readonly class RerankingResult
{
    /**
     * @param list<RerankedDocumentResult> $documents
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $runId,
        public array $documents,
        public string $provider,
        public string $model,
        public array $metadata = [],
    ) {
    }
}
