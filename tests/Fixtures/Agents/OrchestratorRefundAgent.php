<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;

final class OrchestratorRefundAgent implements Agent
{
    public function definition(): AgentDefinition
    {
        return new AgentDefinition(
            key: 'refund.agent',
            displayName: 'Refund Agent',
            requiredCapabilities: ['structured_output'],
            primaryProviderProfile: 'openai-refund',
        );
    }

    public function handle(AgentExecutionContext $context): AgentExecutionResult
    {
        return new AgentExecutionResult(
            kind: AgentExecutionResult::KIND_COMPLETE,
            output: [
            'refund_status' => 'initiated',
            'subscription_id' => $context->payloadValue('subscription_id'),
            'delegated_by_agent' => $context->metadataValue('delegated_by_agent'),
          ],
            summary: 'Refund agent completed successfully.',
        );
    }
}
