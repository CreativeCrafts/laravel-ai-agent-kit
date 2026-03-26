<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Orchestration;

use InvalidArgumentException;

final readonly class DelegationProposal
{
    public const string MODE_DELEGATE_AND_RESUME = 'delegate_and_resume';
    public const string MODE_TRANSFER_CONTROL = 'transfer_control';

    /**
     * @var list<string>
     */
    private const array ALLOWED_MODES = [
      self::MODE_DELEGATE_AND_RESUME,
      self::MODE_TRANSFER_CONTROL,
    ];

    public function __construct(
        public string $mode,
        public string $targetAgent,
        public HandoffPayload $handoff,
    ) {
        if (!in_array($this->mode, self::ALLOWED_MODES, true)) {
            throw new InvalidArgumentException('Delegation proposals mode must be delegate_and_resume or transfer_control.');
        }

        if ($this->targetAgent === '') {
            throw new InvalidArgumentException('Delegation proposals require a non-empty targetAgent.');
        }
    }

    public function transfersControl(): bool
    {
        return $this->mode === self::MODE_TRANSFER_CONTROL;
    }
}
