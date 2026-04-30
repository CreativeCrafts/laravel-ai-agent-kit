<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality;

/**
 * @param array<string, mixed> $metadata
 */
final readonly class ImageGenerationResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $runId,
        public string $imageBase64,
        public ?string $mimeType,
        public string $provider,
        public string $model,
        public int $imageCount,
        public int $promptTokens,
        public int $completionTokens,
        public array $metadata = [],
    ) {
    }
}
