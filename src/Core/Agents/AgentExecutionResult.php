<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Agents;

use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\DelegationProposal;
use InvalidArgumentException;

final readonly class AgentExecutionResult
{
    public const string KIND_COMPLETE = 'complete';
    public const string KIND_CONTINUE = 'continue';
    public const string KIND_DELEGATE = 'delegate';
    public const string KIND_FAIL = 'fail';

    /**
     * @var list<string>
     */
    private const array ALLOWED_KINDS = [
      self::KIND_COMPLETE,
      self::KIND_CONTINUE,
      self::KIND_DELEGATE,
      self::KIND_FAIL,
    ];

    /**
     * @param array<string, mixed> $output
     */
    public function __construct(
        public string $kind,
        public array $output = [],
        public ?DelegationProposal $delegation = null,
        public ?string $summary = null,
    ) {
        if (!in_array($this->kind, self::ALLOWED_KINDS, true)) {
            throw new InvalidArgumentException('Agent execution result kind must be complete, continue, delegate, or fail.');
        }

        if ($this->kind === self::KIND_DELEGATE && !$this->delegation instanceof DelegationProposal) {
            throw new InvalidArgumentException('Agent execution results with kind delegate require a delegation proposal.');
        }

        if ($this->kind !== self::KIND_DELEGATE && $this->delegation instanceof DelegationProposal) {
            throw new InvalidArgumentException('Agent execution results may only carry a delegation proposal when kind is delegate.');
        }

        if ($this->summary === '') {
            throw new InvalidArgumentException('Agent execution result summary must be null or a non-empty string.');
        }
    }

    public function isTerminal(): bool
    {
        return in_array($this->kind, [self::KIND_COMPLETE, self::KIND_FAIL], true);
    }
}
