<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

final readonly class ExecutionRequest
{
    /**
     * @param list<string> $instructions
     * @param list<string> $toolNames
     * @param array<string, mixed> $input
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $runId,
        public string $prompt,
        public array $instructions = [],
        public ?string $provider = null,
        public ?string $model = null,
        public array $toolNames = [],
        public array $input = [],
        public array $metadata = [],
        public ?int $timeout = null,
    ) {
    }
}
