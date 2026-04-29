<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

/**
 * Terminal streaming failure (immutable). No prompt text or raw provider payloads.
 */
final readonly class StreamFailure
{
    public function __construct(
        public string $runId,
        public string $failureCategory,
        public string $exceptionClass,
        public ?string $exceptionMessage = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'run_id' => $this->runId,
            'failure_category' => $this->failureCategory,
            'exception_class' => $this->exceptionClass,
            'exception_message' => $this->exceptionMessage,
        ];
    }
}
