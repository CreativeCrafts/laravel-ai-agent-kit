<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

final readonly class ExecutionResult
{
    /**
     * @param array<string, int> $usage
     * @param array<string, mixed> $metadata
     * @param array<string, mixed>|null $structuredOutput Populated from Laravel\Ai\Responses\StructuredAgentResponse::$structured when a schema drove the call; null otherwise.
     */
    public function __construct(
        public string $runId,
        public string $output,
        public ?string $provider = null,
        public ?string $model = null,
        public array $usage = [],
        public array $metadata = [],
        public ?array $structuredOutput = null,
    ) {
    }
}
