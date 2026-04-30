<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality;

final readonly class TranscriptionSegmentResult
{
    public function __construct(
        public string $text,
        public string $speaker,
        public float $startSeconds,
        public float $endSeconds,
    ) {
    }
}
