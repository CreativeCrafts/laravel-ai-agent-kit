<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;

final class OrchestratorListOutputAgent implements Agent
{
    public function definition(): AgentDefinition
    {
        return new AgentDefinition(
            key: 'list-output.agent',
            displayName: 'List Output Agent',
            requiredCapabilities: ['text_generation'],
            primaryProviderProfile: 'openai-greeting',
        );
    }

    public function handle(AgentExecutionContext $context): AgentExecutionResult
    {
        return new AgentExecutionResult(
            kind: AgentExecutionResult::KIND_COMPLETE,
            output: [
            sprintf('item:%s', (string)$context->payloadValue('0', 'alpha')),
            'item:beta',
          ],
            summary: 'List output agent completed successfully.',
        );
    }
}
