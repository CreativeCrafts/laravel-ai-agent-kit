<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\DelegationProposal;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\HandoffPayload;

final class OrchestratorInvalidDelegationAgent implements Agent
{
    public function definition(): AgentDefinition
    {
        return new AgentDefinition(
            key: 'invalid-delegation.agent',
            displayName: 'Invalid Delegation Agent',
            requiredCapabilities: ['text_generation'],
            primaryProviderProfile: 'openai-invalid-delegation',
            delegationTargets: [],
        );
    }

    public function handle(AgentExecutionContext $context): AgentExecutionResult
    {
        return new AgentExecutionResult(
            kind: AgentExecutionResult::KIND_DELEGATE,
            delegation: new DelegationProposal(
                mode: DelegationProposal::MODE_TRANSFER_CONTROL,
                targetAgent: 'refund.agent',
                handoff: new HandoffPayload(
                    task: 'This target is intentionally invalid.',
                    reason: 'invalid_target_test',
                ),
            ),
            summary: 'Invalid delegation agent proposed a forbidden target.',
        );
    }
}
