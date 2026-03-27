<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\DelegationProposal;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\HandoffPayload;

final class OrchestratorTransferAgent implements Agent
{
    public function definition(): AgentDefinition
    {
        return new AgentDefinition(
            key: 'transfer.agent',
            displayName: 'Transfer Agent',
            requiredCapabilities: ['text_generation'],
            primaryProviderProfile: 'anthropic-transfer',
            delegationTargets: ['refund.agent'],
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
                    task: 'Take ownership of the refund workflow.',
                    reason: 'ownership_transfer',
                    payload: [
                'subscription_id' => $context->payloadValue('subscription_id'),
              ],
                    note: 'Transfer control to the refund specialist.',
                ),
            ),
            summary: 'Transfer agent handed ownership to the refund specialist.',
        );
    }
}
