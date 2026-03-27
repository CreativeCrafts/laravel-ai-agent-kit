<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\DelegationProposal;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\HandoffPayload;

final class OrchestratorPayloadOnlyDelegatorAgent implements Agent
{
    public function definition(): AgentDefinition
    {
        return new AgentDefinition(
            key: 'payload-only-delegator.agent',
            displayName: 'Payload Only Delegator Agent',
            requiredCapabilities: ['text_generation'],
            primaryProviderProfile: 'anthropic-payload-only-delegator',
            delegationTargets: ['history-metadata-probe.agent'],
        );
    }

    public function handle(AgentExecutionContext $context): AgentExecutionResult
    {
        return new AgentExecutionResult(
            kind: AgentExecutionResult::KIND_DELEGATE,
            delegation: new DelegationProposal(
                mode: DelegationProposal::MODE_TRANSFER_CONTROL,
                targetAgent: 'history-metadata-probe.agent',
                handoff: new HandoffPayload(
                    task: 'Inspect delegated context under payload-only mode.',
                    reason: 'history_scope_regression_guard',
                    payload: [
                'probe' => $context->payloadValue('probe', 'payload_only'),
              ],
                    historyMode: HandoffPayload::HISTORY_PAYLOAD_ONLY,
                    requestedOutcome: 'Report visible metadata fields.',
                ),
            ),
            summary: 'Payload-only delegator forwarded the task.',
        );
    }
}
