<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Orchestration;

use InvalidArgumentException;

final readonly class OrchestrationResult
{
    public const string STATUS_COMPLETED = 'completed';
    public const string STATUS_FAILED = 'failed';
    public const string STATUS_CANCELLED = 'cancelled';

    /**
     * @var list<string>
     */
    private const array ALLOWED_STATUSES = [
      self::STATUS_COMPLETED,
      self::STATUS_FAILED,
      self::STATUS_CANCELLED,
    ];

    /**
     * @param array<string, mixed> $finalOutput
     * @param list<ExecutionTraceRecord> $trace
     */
    public function __construct(
        public string $orchestrationId,
        public string $status,
        public string $finalAgent,
        public string $finalExecutionId,
        public array $finalOutput,
        public string $summary,
        public array $trace = [],
    ) {
        if ($this->orchestrationId === '') {
            throw new InvalidArgumentException('Orchestration results require a non-empty orchestrationId.');
        }

        if (!in_array($this->status, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException('Orchestration result status must be completed, failed, or cancelled.');
        }

        if ($this->finalAgent === '') {
            throw new InvalidArgumentException('Orchestration results require a non-empty finalAgent.');
        }

        if ($this->finalExecutionId === '') {
            throw new InvalidArgumentException('Orchestration results require a non-empty finalExecutionId.');
        }

        if ($this->summary === '') {
            throw new InvalidArgumentException('Orchestration results require a non-empty summary.');
        }
    }

    public function completed(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function failed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
