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
     * @return array{
     *   orchestration_id:string,
     *   subject:string,
     *   audio_reference:string,
     *   transcript:string,
     *   summary:string,
     *   recommended_action:string,
     *   confidence:float,
     *   enabled_dimensions:list<string>,
     *   dimensions:array<string, array{name:string,score:int,summary:string,evidence:list<string>}>,
     *   transcription_prompt_name:string,
     *   transcription_prompt_version:?string,
     *   evaluation_prompt_name:string,
     *   evaluation_prompt_version:?string,
     *   orchestration_summary:string,
     *   final_agent:string
     * }
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
          'transcription_prompt_name' => $this->transcriptionPromptName,
          'transcription_prompt_version' => $this->transcriptionPromptVersion,
          'evaluation_prompt_name' => $this->evaluationPromptName,
          'evaluation_prompt_version' => $this->evaluationPromptVersion,
          'orchestration_summary' => $this->orchestrationSummary,
          'final_agent' => $this->finalAgent,
        ];
    }
}
