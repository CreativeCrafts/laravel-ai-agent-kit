<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality;

use InvalidArgumentException;

/**
 * @param array<string, mixed> $metadata
 */
final readonly class TranscriptionRequest
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $runId,
        public string $base64Audio,
        public ?string $mimeType = null,
        public ?string $language = null,
        public bool $diarize = false,
        public ?int $timeout = null,
        public ?string $provider = null,
        public ?string $model = null,
        public array $metadata = [],
        public ?string $prompt = null,
        public ?TranscriptionProviderOptions $providerOptions = null,
    ) {
        if ($this->runId === '') {
            throw new InvalidArgumentException('Transcription requests require a non-empty runId.');
        }

        if ($this->base64Audio === '') {
            throw new InvalidArgumentException('Transcription requests require non-empty base64 audio payload.');
        }

        if ($this->timeout !== null && $this->timeout < 1) {
            throw new InvalidArgumentException('Transcription request timeout must be null or >= 1.');
        }

        if ($this->prompt !== null && trim($this->prompt) === '') {
            throw new InvalidArgumentException('Transcription request prompt must be null or a non-empty string.');
        }

        if ($this->providerOptions?->hasChunkingStrategy() === true && !$this->diarize) {
            throw new InvalidArgumentException('Transcription request chunkingStrategy is only supported when diarize is true.');
        }
    }
}
