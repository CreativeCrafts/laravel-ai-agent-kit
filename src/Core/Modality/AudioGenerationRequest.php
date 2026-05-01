<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality;

use InvalidArgumentException;
use Laravel\Ai\Audio;
use Laravel\Ai\PendingResponses\PendingAudioGeneration;

/**
 * Text-to-speech / audio generation request aligned with {@see Audio::of} and
 * {@see PendingAudioGeneration}.
 *
 * @param array<string, mixed> $metadata
 */
final readonly class AudioGenerationRequest
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $runId,
        public string $text,
        public ?string $voice = null,
        public bool $maleVoice = false,
        public ?string $instructions = null,
        public ?int $timeout = null,
        public ?string $provider = null,
        public ?string $model = null,
        public array $metadata = [],
    ) {
        if ($this->runId === '') {
            throw new InvalidArgumentException('Audio generation requests require a non-empty runId.');
        }

        if ($this->text === '') {
            throw new InvalidArgumentException('Audio generation requests require non-empty text.');
        }

        if ($this->timeout !== null && $this->timeout < 1) {
            throw new InvalidArgumentException('Audio generation request timeout must be null or >= 1.');
        }
    }
}
