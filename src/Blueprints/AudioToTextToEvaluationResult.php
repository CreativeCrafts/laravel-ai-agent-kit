<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints;

use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\ExecutionTraceRecord;
use InvalidArgumentException;

final readonly class AudioToTextToEvaluationResult
{
    /**
     * @param list<string> $enabledDimensions
     * @param array<string, TextToStructuredEvaluationDimensionResult> $dimensions
     * @param array<string, mixed> $structuredOutput
     * @param list<array{text:string,speaker:string,start_seconds:float,end_seconds:float}> $segments
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $usage
     * @param list<ExecutionTraceRecord> $trace
     */
    public function __construct(
        public string $orchestrationId,
        public string $subject,
        public string $audioReference,
        public string $transcript,
        public string $summary,
        public string $recommendedAction,
        public float $confidence,
        public array $enabledDimensions,
        public array $dimensions,
        public string $transcriptionPromptName,
        public ?string $transcriptionPromptVersion,
        public string $evaluationPromptName,
        public ?string $evaluationPromptVersion,
        public string $orchestrationSummary,
        public string $finalAgent,
        public array $trace = [],
        public array $structuredOutput = [],
        public array $segments = [],
        public array $metadata = [],
        public ?string $transcriptionProvider = null,
        public ?string $transcriptionModel = null,
        public ?string $evaluationProvider = null,
        public ?string $evaluationModel = null,
        public array $usage = [],
    ) {
        foreach (
          [
            'orchestrationId' => $this->orchestrationId,
            'subject' => $this->subject,
            'audioReference' => $this->audioReference,
            'transcript' => $this->transcript,
            'summary' => $this->summary,
            'recommendedAction' => $this->recommendedAction,
            'transcriptionPromptName' => $this->transcriptionPromptName,
            'evaluationPromptName' => $this->evaluationPromptName,
            'orchestrationSummary' => $this->orchestrationSummary,
            'finalAgent' => $this->finalAgent,
          ] as $field => $value
        ) {
            if ($value === '') {
                throw new InvalidArgumentException(
                    sprintf(
                        'AudioToTextToEvaluation results require a non-empty [%s].',
                        $field,
                    ),
                );
            }
        }

        foreach (
          [
            'transcriptionPromptVersion' => $this->transcriptionPromptVersion,
            'evaluationPromptVersion' => $this->evaluationPromptVersion,
            'transcriptionProvider' => $this->transcriptionProvider,
            'transcriptionModel' => $this->transcriptionModel,
            'evaluationProvider' => $this->evaluationProvider,
            'evaluationModel' => $this->evaluationModel,
          ] as $field => $value
        ) {
            if ($value === '') {
                throw new InvalidArgumentException(
                    sprintf(
                        'AudioToTextToEvaluation result field [%s] must be null or a non-empty string.',
                        $field,
                    ),
                );
            }
        }

        if ($this->confidence < 0.0 || $this->confidence > 1.0) {
            throw new InvalidArgumentException('AudioToTextToEvaluation confidence must be between 0.0 and 1.0.');
        }

        if ($this->enabledDimensions === []) {
            throw new InvalidArgumentException('AudioToTextToEvaluation results require at least one enabled dimension.');
        }

        foreach ($this->enabledDimensions as $dimension) {
            if ($dimension === '') {
                throw new InvalidArgumentException('AudioToTextToEvaluation enabled dimensions must be non-empty strings.');
            }

            if (!array_key_exists($dimension, $this->dimensions)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'AudioToTextToEvaluation results must contain a dimension payload for [%s].',
                        $dimension,
                    ),
                );
            }
        }

        foreach (array_keys($this->dimensions) as $name) {
            if ($name === '') {
                throw new InvalidArgumentException('AudioToTextToEvaluation dimension keys must be non-empty strings.');
            }
        }
    }

    public function dimension(string $name): ?TextToStructuredEvaluationDimensionResult
    {
        return $this->dimensions[$name] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $dimensions = array_map(static function ($dimension) {
            return $dimension->toArray();
        }, $this->dimensions);

        return [
          'orchestration_id' => $this->orchestrationId,
          'subject' => $this->subject,
          'audio_reference' => $this->audioReference,
          'transcript' => $this->transcript,
          'summary' => $this->summary,
          'recommended_action' => $this->recommendedAction,
          'confidence' => $this->confidence,
          'enabled_dimensions' => $this->enabledDimensions,
          'dimensions' => $dimensions,
          'structured_output' => $this->structuredOutput,
          'segments' => $this->segments,
          'metadata' => $this->metadata,
          'transcription_provider' => $this->transcriptionProvider,
          'transcription_model' => $this->transcriptionModel,
          'evaluation_provider' => $this->evaluationProvider,
          'evaluation_model' => $this->evaluationModel,
          'usage' => $this->usage,
          'transcription_prompt_name' => $this->transcriptionPromptName,
          'transcription_prompt_version' => $this->transcriptionPromptVersion,
          'evaluation_prompt_name' => $this->evaluationPromptName,
          'evaluation_prompt_version' => $this->evaluationPromptVersion,
          'orchestration_summary' => $this->orchestrationSummary,
          'final_agent' => $this->finalAgent,
        ];
    }
}
