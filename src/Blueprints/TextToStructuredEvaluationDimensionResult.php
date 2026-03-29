<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints;

use InvalidArgumentException;

final readonly class TextToStructuredEvaluationDimensionResult
{
    /**
     * @param list<string> $evidence
     */
    public function __construct(
        public string $name,
        public int $score,
        public string $summary,
        public array $evidence = [],
    ) {
        if ($this->name === '') {
            throw new InvalidArgumentException('TextToStructuredEvaluation dimension results require a non-empty name.');
        }

        if ($this->score < 0 || $this->score > 5) {
            throw new InvalidArgumentException('TextToStructuredEvaluation dimension scores must be between 0 and 5.');
        }

        if ($this->summary === '') {
            throw new InvalidArgumentException('TextToStructuredEvaluation dimension results require a non-empty summary.');
        }

        foreach ($this->evidence as $item) {
            if ($item === '') {
                throw new InvalidArgumentException('TextToStructuredEvaluation dimension evidence entries must be non-empty strings.');
            }
        }
    }

    /**
     * @return array{name:string,score:int,summary:string,evidence:list<string>}
     */
    public function toArray(): array
    {
        return [
          'name' => $this->name,
          'score' => $this->score,
          'summary' => $this->summary,
          'evidence' => $this->evidence,
        ];
    }
}
