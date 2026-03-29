<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints;

use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use InvalidArgumentException;

final readonly class TextToStructuredEvaluationRequest
{
    /**
     * @param list<string> $enabledDimensions
     * @param array<string, scalar|null> $promptVariables
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $subject,
        public string $text,
        public array $enabledDimensions = ['accuracy', 'clarity', 'completeness'],
        public string $promptName = 'text-to-structured-evaluation.specialist',
        public ?string $promptVersion = null,
        public array $promptVariables = [],
        public array $metadata = [],
        public ?ConversationId $conversationId = null,
        public bool $storeConversation = false,
        public bool $continueConversation = false,
        public ?string $model = null,
    ) {
        if ($this->subject === '') {
            throw new InvalidArgumentException('TextToStructuredEvaluation requests require a non-empty subject.');
        }

        if ($this->text === '') {
            throw new InvalidArgumentException('TextToStructuredEvaluation requests require non-empty text.');
        }

        if ($this->promptName === '') {
            throw new InvalidArgumentException('TextToStructuredEvaluation requests require a non-empty promptName.');
        }

        if ($this->promptVersion === '') {
            throw new InvalidArgumentException('TextToStructuredEvaluation promptVersion must be null or a non-empty string.');
        }

        if ($this->model === '') {
            throw new InvalidArgumentException('TextToStructuredEvaluation model must be null or a non-empty string.');
        }

        if ($this->enabledDimensions === []) {
            throw new InvalidArgumentException('TextToStructuredEvaluation requests require at least one enabled dimension.');
        }

        $normalizedDimensions = [];

        foreach ($this->enabledDimensions as $dimension) {
            if ($dimension === '') {
                throw new InvalidArgumentException('TextToStructuredEvaluation enabledDimensions entries must be non-empty strings.');
            }

            if (in_array($dimension, $normalizedDimensions, true)) {
                throw new InvalidArgumentException('TextToStructuredEvaluation enabledDimensions entries must be unique.');
            }

            $normalizedDimensions[] = $dimension;
        }

        if ($this->continueConversation && !$this->conversationId instanceof ConversationId) {
            throw new InvalidArgumentException('TextToStructuredEvaluation requests that continue a conversation require a conversationId.');
        }
    }

    /**
     * @return list<string>
     */
    public function dimensionList(): array
    {
        return $this->enabledDimensions;
    }
}
