<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality;

/**
 * Successful audio generation: base64-encoded audio (as returned by Laravel AI) plus metadata.
 *
 * @param array<string, mixed> $metadata
 */
final readonly class AudioGenerationResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $runId,
        public string $audioBase64,
        public ?string $mimeType,
        public string $provider,
        public string $model,
        public array $metadata = [],
    ) {
    }
}
