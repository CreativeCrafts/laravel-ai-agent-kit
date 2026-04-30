<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality;

final readonly class RerankedDocumentResult
{
    public function __construct(
        public int $originalIndex,
        public string $document,
        public float $score,
    ) {
    }
}
