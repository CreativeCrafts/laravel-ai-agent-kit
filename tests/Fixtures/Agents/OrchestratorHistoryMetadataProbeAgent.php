<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;

final class OrchestratorHistoryMetadataProbeAgent implements Agent
{
    public function definition(): AgentDefinition
    {
        return new AgentDefinition(
            key: 'history-metadata-probe.agent',
            displayName: 'History Metadata Probe Agent',
            requiredCapabilities: ['structured_output'],
            primaryProviderProfile: 'openai-history-probe',
        );
    }

    public function handle(AgentExecutionContext $context): AgentExecutionResult
    {
        return new AgentExecutionResult(
            kind: AgentExecutionResult::KIND_COMPLETE,
            output: [
            'payload_probe' => $context->payloadValue('probe'),
            'seen_sensitive_key' => $context->metadataValue('sensitive_key', 'missing'),
            'seen_internal_marker' => $context->metadataValue('_orchestrator.internal_marker', 'missing'),
            'seen_delegated_by_agent' => $context->metadataValue('_orchestrator.delegated_by_agent'),
            'seen_requested_outcome' => $context->metadataValue('_orchestrator.requested_outcome'),
            'history_summary' => $context->historySummary,
          ],
            summary: 'History metadata probe completed.',
        );
    }
}
