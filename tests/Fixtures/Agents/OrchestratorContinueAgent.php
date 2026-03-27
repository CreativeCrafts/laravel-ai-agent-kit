<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;

final class OrchestratorContinueAgent implements Agent
{
    public function definition(): AgentDefinition
    {
        return new AgentDefinition(
            key: 'continue.agent',
            displayName: 'Continue Agent',
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
                'step' => 2,
                'message' => (string)$context->payloadValue('message'),
              ],
                summary: 'Continue agent completed after reinvocation.',
            );
        }

        return new AgentExecutionResult(
            kind: AgentExecutionResult::KIND_CONTINUE,
            output: [
            'step' => 1,
            'message' => 'Continue the same agent workflow.',
          ],
            summary: 'Continue agent requested one more execution turn.',
        );
    }
}
