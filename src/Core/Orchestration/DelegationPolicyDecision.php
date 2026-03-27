<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Orchestration;

use InvalidArgumentException;

final readonly class DelegationPolicyDecision
{
    public function __construct(
        public DelegationProposal $proposal,
        public DelegationPolicyMode $mode,
        public bool $rewritten = false,
        public ?string $originalTargetAgent = null,
    ) {
        if ($this->rewritten && ($this->originalTargetAgent === null || $this->originalTargetAgent === '')) {
            throw new InvalidArgumentException('Delegation policy decisions that rewrite targets require a non-empty originalTargetAgent.');
        }

        if (!$this->rewritten && $this->originalTargetAgent !== null) {
            throw new InvalidArgumentException('Delegation policy decisions without rewrites must not carry an originalTargetAgent.');
        }
    }
}
