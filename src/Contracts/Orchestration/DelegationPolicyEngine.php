<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration;

use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\DelegationPolicyDecision;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\DelegationProposal;

interface DelegationPolicyEngine
{
    public function evaluate(AgentDefinition $agentDefinition, DelegationProposal $proposal): DelegationPolicyDecision;
}
