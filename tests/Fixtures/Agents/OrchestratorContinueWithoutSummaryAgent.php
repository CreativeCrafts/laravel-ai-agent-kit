<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;

final class OrchestratorContinueWithoutSummaryAgent implements Agent
{
    public function definition(): AgentDefinition
    {
        return new AgentDefinition(
            key: 'continue-no-summary.agent',
            displayName: 'Continue Without Summary Agent',
            requiredCapabilities: ['text_generation'],
            primaryProviderProfile: 'openai-continue',
        );
    }

    public function handle(AgentExecutionContext $context): AgentExecutionResult
    {
        if ($context->metadataValue('_orchestrator.continued_from_execution_id') !== null) {
            return new AgentExecutionResult(
                kind: AgentExecutionResult::KIND_COMPLETE,
                output: [
                'continued' => true,
                'history_summary' => $context->historySummary,
              ],
                summary: 'Continue-without-summary agent completed after reinvocation.',
            );
        }

        return new AgentExecutionResult(
            kind: AgentExecutionResult::KIND_CONTINUE,
            output: [
            'continued' => false,
          ],
            summary: null,
        );
    }
}
