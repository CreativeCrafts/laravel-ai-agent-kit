<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\DelegationProposal;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\HandoffPayload;

final class OrchestratorRewriteDelegationAgent implements Agent
{
    public function definition(): AgentDefinition
    {
        return new AgentDefinition(
            key: 'rewrite-delegation.agent',
            displayName: 'Rewrite Delegation Agent',
            requiredCapabilities: ['text_generation'],
            primaryProviderProfile: 'openai-rewrite-delegation',
            delegationTargets: ['legacy-refund.agent'],
        );
    }

    public function handle(AgentExecutionContext $context): AgentExecutionResult
    {
        return new AgentExecutionResult(
            kind: AgentExecutionResult::KIND_DELEGATE,
            delegation: new DelegationProposal(
                mode: DelegationProposal::MODE_TRANSFER_CONTROL,
                targetAgent: 'legacy-refund.agent',
                handoff: new HandoffPayload(
                    task: 'Rewrite this target to the refund specialist.',
                    reason: 'rewrite_test',
                    payload: [
                'subscription_id' => $context->payloadValue('subscription_id'),
              ],
                ),
            ),
            summary: 'Rewrite delegator proposed a legacy refund target.',
        );
    }
}
