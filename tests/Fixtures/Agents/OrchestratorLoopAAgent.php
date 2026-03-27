<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\DelegationProposal;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\HandoffPayload;

final class OrchestratorLoopAAgent implements Agent
{
    public function definition(): AgentDefinition
    {
        return new AgentDefinition(
            key: 'loop-a.agent',
            displayName: 'Loop A Agent',
            requiredCapabilities: ['text_generation'],
            primaryProviderProfile: 'openai-loop-a',
            delegationTargets: ['loop-b.agent'],
        );
    }

    public function handle(AgentExecutionContext $context): AgentExecutionResult
    {
        return new AgentExecutionResult(
            kind: AgentExecutionResult::KIND_DELEGATE,
            delegation: new DelegationProposal(
                mode: DelegationProposal::MODE_TRANSFER_CONTROL,
                targetAgent: 'loop-b.agent',
                handoff: new HandoffPayload(
                    task: 'Continue the loop in B.',
                    reason: 'loop_test',
                ),
            ),
            summary: 'Loop A delegated to Loop B.',
        );
    }
}
