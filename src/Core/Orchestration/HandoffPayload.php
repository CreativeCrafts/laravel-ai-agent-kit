<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Orchestration;

use InvalidArgumentException;

final readonly class HandoffPayload
{
    public const string HISTORY_PAYLOAD_ONLY = 'payload_only';
    public const string HISTORY_PAYLOAD_PLUS_SUMMARY = 'payload_plus_summary';
    public const string HISTORY_FULL = 'full_history';

    /**
     * @var list<string>
     */
    private const array ALLOWED_HISTORY_MODES = [
      self::HISTORY_PAYLOAD_ONLY,
      self::HISTORY_PAYLOAD_PLUS_SUMMARY,
      self::HISTORY_FULL,
    ];

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $task,
        public string $reason,
        public array $payload = [],
        public string $historyMode = self::HISTORY_PAYLOAD_PLUS_SUMMARY,
        public ?string $note = null,
        public ?string $requestedOutcome = null,
    ) {
        if ($this->task === '') {
            throw new InvalidArgumentException('Handoff payloads require a non-empty task.');
        }

        if ($this->reason === '') {
            throw new InvalidArgumentException('Handoff payloads require a non-empty reason.');
        }

        if (!in_array($this->historyMode, self::ALLOWED_HISTORY_MODES, true)) {
            throw new InvalidArgumentException('Handoff payload historyMode must be one of: payload_only, payload_plus_summary, or full_history.');
        }

        if ($this->note === '') {
            throw new InvalidArgumentException('Handoff payload note must be null or a non-empty string.');
        }

        if ($this->requestedOutcome === '') {
            throw new InvalidArgumentException('Handoff payload requestedOutcome must be null or a non-empty string.');
        }
    }

    public function sharesFullHistory(): bool
    {
        return $this->historyMode === self::HISTORY_FULL;
    }
}
