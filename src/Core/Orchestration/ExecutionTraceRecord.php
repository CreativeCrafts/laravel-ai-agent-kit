<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Orchestration;

use InvalidArgumentException;

final readonly class ExecutionTraceRecord
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $orchestrationId,
        public string $executionId,
        public ?string $parentExecutionId,
        public string $agentKey,
        public string $providerProfile,
        public string $resultKind,
        public ?string $targetAgent = null,
        public ?string $summary = null,
        public array $metadata = [],
    ) {
        if ($this->orchestrationId === '') {
            throw new InvalidArgumentException('Execution trace records require a non-empty orchestrationId.');
        }

        if ($this->executionId === '') {
            throw new InvalidArgumentException('Execution trace records require a non-empty executionId.');
        }

        if ($this->parentExecutionId === '') {
            throw new InvalidArgumentException('Execution trace records parentExecutionId must be null or a non-empty string.');
        }

        if ($this->agentKey === '') {
            throw new InvalidArgumentException('Execution trace records require a non-empty agentKey.');
        }

        if ($this->providerProfile === '') {
            throw new InvalidArgumentException('Execution trace records require a non-empty providerProfile.');
        }

        if ($this->resultKind === '') {
            throw new InvalidArgumentException('Execution trace records require a non-empty resultKind.');
        }

        if ($this->targetAgent === '') {
            throw new InvalidArgumentException('Execution trace record targetAgent must be null or a non-empty string.');
        }

        if ($this->summary === '') {
            throw new InvalidArgumentException('Execution trace record summary must be null or a non-empty string.');
        }
    }

    public function hasParentExecution(): bool
    {
        return $this->parentExecutionId !== null;
    }
}
