<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

/**
 * Incremental streaming payload (immutable).
 */
final readonly class StreamChunk
{
    /**
     * @param array<string, mixed> $metadata Redacted, non-sensitive context (e.g. message identifiers).
     */
    public function __construct(
        public string $runId,
        public int $sequence,
        public string $type,
        public string $textDelta,
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
            'sequence' => $this->sequence,
            'type' => $this->type,
            'text_delta' => $this->textDelta,
            'metadata' => $this->metadata,
        ];
    }
}
