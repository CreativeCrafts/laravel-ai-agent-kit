<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints;

use Closure;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionAudioSource;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionProviderOptions;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\GenerationOptions;
use InvalidArgumentException;
use Laravel\Ai\ObjectSchema;

/**
 * @param list<string> $instructions
 * @param array<string, mixed> $metadata
 */
final readonly class AudioImageStructuredEvaluationRequest
{
    /**
     * @param list<string> $instructions
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $runId,
        public TranscriptionAudioSource $audio,
        public EvaluationImageInput $image,
        public string $evaluationPrompt,
        public Closure|ObjectSchema|string $schema,
        public array $instructions = [],
        public ?string $transcriptionPrompt = null,
        public ?string $transcriptionProvider = null,
        public ?string $transcriptionModel = null,
        public ?string $evaluationProvider = null,
        public ?string $evaluationModel = null,
        public ?GenerationOptions $generationOptions = null,
        public ?TranscriptionProviderOptions $transcriptionProviderOptions = null,
        public ?string $language = null,
        public bool $diarize = false,
        public ?int $transcriptionTimeout = null,
        public ?int $evaluationTimeout = null,
        public bool $allowEmptyTranscript = false,
        public array $metadata = [],
    ) {
        if ($this->runId === '') {
            throw new InvalidArgumentException('Audio-image structured evaluation requests require a non-empty runId.');
        }

        if ($this->evaluationPrompt === '') {
            throw new InvalidArgumentException('Audio-image structured evaluation requests require a non-empty evaluation prompt.');
        }

        foreach ($this->instructions as $index => $instruction) {
            if ($instruction === '') {
                throw new InvalidArgumentException(sprintf('Audio-image structured evaluation instruction at index [%d] must be a non-empty string.', $index));
            }
        }

        if (is_string($this->schema)) {
            if ($this->schema === '') {
                throw new InvalidArgumentException('Audio-image structured evaluation schema class-string must be non-empty.');
            }

            if (!class_exists($this->schema)) {
                throw new InvalidArgumentException(sprintf('Audio-image structured evaluation schema class-string [%s] does not exist.', $this->schema));
            }
        }

        if ($this->transcriptionPrompt !== null && trim($this->transcriptionPrompt) === '') {
            throw new InvalidArgumentException('Audio-image structured evaluation transcription prompt must be null or a non-empty string.');
        }

        if ($this->transcriptionTimeout !== null && $this->transcriptionTimeout < 1) {
            throw new InvalidArgumentException('Audio-image structured evaluation transcription timeout must be null or >= 1.');
        }

        if ($this->evaluationTimeout !== null && $this->evaluationTimeout < 1) {
            throw new InvalidArgumentException('Audio-image structured evaluation timeout must be null or >= 1.');
        }
    }
}
