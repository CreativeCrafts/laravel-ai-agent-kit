<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\DelegationProposal;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\HandoffPayload;

final class OrchestratorNullNoteDelegatorAgent implements Agent
{
    public function definition(): AgentDefinition
    {
        return new AgentDefinition(
            key: 'null-note-delegator.agent',
            displayName: 'Null Note Delegator Agent',
            requiredCapabilities: ['text_generation'],
            primaryProviderProfile: 'anthropic-null-note-delegator',
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
                    task: 'Inspect delegated context when note is omitted.',
                    reason: 'preserve_existing_history_summary',
                    payload: [
                'probe' => $context->payloadValue('probe', 'null_note'),
              ],
                    requestedOutcome: 'Report preserved history summary.',
                ),
            ),
            summary: 'Null-note delegator forwarded the task.',
        );
    }
}
