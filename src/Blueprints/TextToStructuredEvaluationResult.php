<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints;

use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\ExecutionTraceRecord;
use InvalidArgumentException;

final readonly class TextToStructuredEvaluationResult
{
    /**
     * @param list<string> $enabledDimensions
     * @param array<string, TextToStructuredEvaluationDimensionResult> $dimensions
     * @param list<ExecutionTraceRecord> $trace
     */
    public function __construct(
        public string $orchestrationId,
        public string $subject,
        public string $summary,
        public string $recommendedAction,
        public float $confidence,
        public array $enabledDimensions,
        public array $dimensions,
        public string $orchestrationSummary,
        public string $finalAgent,
        public string $promptName,
        public ?string $promptVersion,
        public array $trace = [],
        public ?string $structuredEvaluationPath = null,
        public bool $structuredEvaluationRepaired = false,
    ) {
        if ($this->orchestrationId === '') {
            throw new InvalidArgumentException('TextToStructuredEvaluation results require a non-empty orchestrationId.');
        }

        if ($this->subject === '') {
            throw new InvalidArgumentException('TextToStructuredEvaluation results require a non-empty subject.');
        }

        if ($this->summary === '') {
            throw new InvalidArgumentException('TextToStructuredEvaluation results require a non-empty summary.');
        }

        if ($this->recommendedAction === '') {
            throw new InvalidArgumentException('TextToStructuredEvaluation results require a non-empty recommendedAction.');
        }

        if ($this->confidence < 0.0 || $this->confidence > 1.0) {
            throw new InvalidArgumentException('TextToStructuredEvaluation confidence must be between 0.0 and 1.0.');
        }

        if ($this->enabledDimensions === []) {
            throw new InvalidArgumentException('TextToStructuredEvaluation results require at least one enabled dimension.');
        }

        if ($this->orchestrationSummary === '') {
            throw new InvalidArgumentException('TextToStructuredEvaluation results require a non-empty orchestrationSummary.');
        }

        if ($this->finalAgent === '') {
            throw new InvalidArgumentException('TextToStructuredEvaluation results require a non-empty finalAgent.');
        }

        if ($this->promptName === '') {
            throw new InvalidArgumentException('TextToStructuredEvaluation results require a non-empty promptName.');
        }

        if ($this->promptVersion === '') {
            throw new InvalidArgumentException('TextToStructuredEvaluation promptVersion must be null or a non-empty string.');
        }

        if ($this->structuredEvaluationPath !== null
            && !in_array($this->structuredEvaluationPath, ['structured_output', 'text_normalization'], true)) {
            throw new InvalidArgumentException('TextToStructuredEvaluation structuredEvaluationPath must be structured_output, text_normalization, or null.');
        }

        foreach ($this->enabledDimensions as $dimension) {
            if ($dimension === '') {
                throw new InvalidArgumentException('TextToStructuredEvaluation enabled dimensions must be non-empty strings.');
            }

            if (!array_key_exists($dimension, $this->dimensions)) {
                throw new InvalidArgumentException(
                    sprintf('TextToStructuredEvaluation results must contain a dimension payload for [%s].', $dimension),
                );
            }
        }

        foreach (array_keys($this->dimensions) as $name) {
            if ($name === '') {
                throw new InvalidArgumentException('TextToStructuredEvaluation dimension keys must be non-empty strings.');
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
     *   summary:string,
     *   recommended_action:string,
     *   confidence:float,
     *   enabled_dimensions:list<string>,
     *   dimensions:array<string, array{name:string,score:int,summary:string,evidence:list<string>}>,
     *   orchestration_summary:string,
     *   final_agent:string,
     *   prompt_name:string,
     *   prompt_version:?string,
     *   structured_evaluation_path:?string,
     *   structured_evaluation_repaired:bool
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
          'summary' => $this->summary,
          'recommended_action' => $this->recommendedAction,
          'confidence' => $this->confidence,
          'enabled_dimensions' => $this->enabledDimensions,
          'dimensions' => $dimensions,
          'orchestration_summary' => $this->orchestrationSummary,
          'final_agent' => $this->finalAgent,
          'prompt_name' => $this->promptName,
          'prompt_version' => $this->promptVersion,
          'structured_evaluation_path' => $this->structuredEvaluationPath,
          'structured_evaluation_repaired' => $this->structuredEvaluationRepaired,
        ];
    }
}
