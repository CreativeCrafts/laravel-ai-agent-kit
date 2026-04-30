<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality;

use InvalidArgumentException;

/**
 * @param array<string, mixed> $metadata
 */
final readonly class ImageGenerationRequest
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $runId,
        public string $prompt,
        public ?string $size = null,
        public ?string $quality = null,
        public ?int $timeout = null,
        public ?string $provider = null,
        public ?string $model = null,
        public array $metadata = [],
    ) {
        if ($this->runId === '') {
            throw new InvalidArgumentException('Image generation requests require a non-empty runId.');
        }

        if ($this->prompt === '') {
            throw new InvalidArgumentException('Image generation requests require a non-empty prompt.');
        }

        if ($this->size !== null && !in_array($this->size, ['3:2', '2:3', '1:1'], true)) {
            throw new InvalidArgumentException('Image generation size must be one of 3:2, 2:3, 1:1, or null.');
        }

        if ($this->quality !== null && !in_array($this->quality, ['low', 'medium', 'high'], true)) {
            throw new InvalidArgumentException('Image generation quality must be one of low, medium, high, or null.');
        }
    }
}
