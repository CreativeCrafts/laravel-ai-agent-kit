<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints;

use Closure;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use InvalidArgumentException;

final readonly class AudioToTextToEvaluationRequest
{
    /**
     * @param list<string> $enabledDimensions
     * @param array<string, scalar|null> $transcriptionPromptVariables
     * @param array<string, scalar|null> $evaluationPromptVariables
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $subject,
        public string $audioReference,
        public ?string $audioMimeType = null,
        public array $enabledDimensions = ['accuracy', 'clarity', 'completeness'],
        public string $transcriptionPromptName = 'audio-to-text-to-evaluation.transcription',
        public ?string $transcriptionPromptVersion = null,
        public array $transcriptionPromptVariables = [],
        public string $evaluationPromptName = 'text-to-structured-evaluation.specialist',
        public ?string $evaluationPromptVersion = null,
        public array $evaluationPromptVariables = [],
        public array $metadata = [],
        public ?ConversationId $conversationId = null,
        public bool $storeConversation = false,
        public bool $continueConversation = false,
        public ?string $transcriptionModel = null,
        public ?string $evaluationModel = null,
        public Closure|object|string|null $schema = null,
    ) {
        if ($this->subject === '') {
            throw new InvalidArgumentException('AudioToTextToEvaluation requests require a non-empty subject.');
        }

        if ($this->audioReference === '') {
            throw new InvalidArgumentException('AudioToTextToEvaluation requests require a non-empty audioReference.');
        }

        foreach (
          [
            'audioMimeType' => $this->audioMimeType,
            'transcriptionPromptName' => $this->transcriptionPromptName,
            'transcriptionPromptVersion' => $this->transcriptionPromptVersion,
            'evaluationPromptName' => $this->evaluationPromptName,
            'evaluationPromptVersion' => $this->evaluationPromptVersion,
            'transcriptionModel' => $this->transcriptionModel,
            'evaluationModel' => $this->evaluationModel,
          ] as $field => $value
        ) {
            if ($value === '') {
                throw new InvalidArgumentException(
                    sprintf(
                        'AudioToTextToEvaluation request field [%s] must be null or a non-empty string.',
                        $field,
                    ),
                );
            }
        }

        if (is_string($this->schema)) {
            if ($this->schema === '') {
                throw new InvalidArgumentException('AudioToTextToEvaluation request schema class-string must be non-empty.');
            }

            if (!class_exists($this->schema)) {
                throw new InvalidArgumentException(
                    sprintf('AudioToTextToEvaluation request schema class-string [%s] does not exist.', $this->schema),
                );
            }
        }

        if ($this->enabledDimensions === []) {
            throw new InvalidArgumentException('AudioToTextToEvaluation requests require at least one enabled dimension.');
        }

        $normalizedDimensions = [];

        foreach ($this->enabledDimensions as $dimension) {
            if ($dimension === '') {
                throw new InvalidArgumentException('AudioToTextToEvaluation enabledDimensions entries must be non-empty strings.');
            }

            if (in_array($dimension, $normalizedDimensions, true)) {
                throw new InvalidArgumentException('AudioToTextToEvaluation enabledDimensions entries must be unique.');
            }

            $normalizedDimensions[] = $dimension;
        }

        if ($this->continueConversation && !$this->conversationId instanceof ConversationId) {
            throw new InvalidArgumentException('AudioToTextToEvaluation requests that continue a conversation require a conversationId.');
        }
    }

    /**
     * @return list<string>
     */
    public function dimensionList(): array
    {
        return $this->enabledDimensions;
    }

    public function hasCustomSchema(): bool
    {
        return $this->schema !== null;
    }
}
