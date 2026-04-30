<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality;

/**
 * @param list<TranscriptionSegmentResult> $segments
 * @param array<string, mixed> $metadata
 */
final readonly class TranscriptionResult
{
    /**
     * @param list<TranscriptionSegmentResult> $segments
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $runId,
        public string $transcript,
        public string $provider,
        public string $model,
        public int $promptTokens,
        public int $completionTokens,
        public array $segments = [],
        public array $metadata = [],
    ) {
    }
}
