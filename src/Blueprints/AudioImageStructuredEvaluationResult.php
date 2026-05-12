<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints;

/**
 * @param array<string, mixed> $structuredOutput
 * @param array<string, int> $usage
 * @param array<string, mixed> $metadata
 */
final readonly class AudioImageStructuredEvaluationResult
{
    /**
     * @param array<string, mixed> $structuredOutput
     * @param array<string, int> $usage
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $runId,
        public string $transcript,
        public array $structuredOutput,
        public string $output,
        public array $usage = [],
        public array $metadata = [],
        public ?string $transcriptionProvider = null,
        public ?string $transcriptionModel = null,
        public ?string $evaluationProvider = null,
        public ?string $evaluationModel = null,
    ) {
    }
}
