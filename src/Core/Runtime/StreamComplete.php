<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

/**
 * Terminal successful completion of a streaming run (immutable).
 *
 * @param array<string, int> $usage
 * @param array<string, mixed> $metadata
 */
final readonly class StreamComplete
{
    /**
     * @param array<string, int> $usage
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $runId,
        public string $output,
        public string $provider,
        public string $model,
        public array $usage,
        public array $metadata = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'run_id' => $this->runId,
            'output' => $this->output,
            'provider' => $this->provider,
            'model' => $this->model,
            'usage' => $this->usage,
            'metadata' => $this->metadata,
        ];
    }
}
