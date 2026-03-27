<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\DelegationProposal;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\HandoffPayload;

final class OrchestratorSupportAgent implements Agent
{
    public function definition(): AgentDefinition
    {
        return new AgentDefinition(
            key: 'support.agent',
            displayName: 'Support Agent',
            requiredCapabilities: ['text_generation'],
            primaryProviderProfile: 'anthropic-support',
            fallbackProviderProfiles: ['openai-support'],
            delegationTargets: ['refund.agent'],
        );
    }

    public function handle(AgentExecutionContext $context): AgentExecutionResult
    {
        if ($context->payloadValue('delegated_result') !== null) {
            return new AgentExecutionResult(
                kind: AgentExecutionResult::KIND_COMPLETE,
                output: [
                'workflow' => 'support_refund',
                'delegated_result' => $context->payloadValue('delegated_result'),
                'delegated_agent' => $context->payloadValue('delegated_agent'),
              ],
                summary: 'Support agent resumed after specialist delegation.',
            );
        }

        return new AgentExecutionResult(
            kind: AgentExecutionResult::KIND_DELEGATE,
            delegation: new DelegationProposal(
                mode: DelegationProposal::MODE_DELEGATE_AND_RESUME,
                targetAgent: 'refund.agent',
                handoff: new HandoffPayload(
                    task: 'Process the refund request.',
                    reason: 'refund_requested',
                    payload: [
                'subscription_id' => $context->payloadValue('subscription_id'),
              ],
                    note: 'Customer requested a refund after cancellation.',
                    requestedOutcome: 'Refund initiated status.',
                ),
            ),
            summary: 'Support agent delegated refund processing.',
        );
    }
}
