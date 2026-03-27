<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;

final class OrchestratorGreetingAgent implements Agent
{
    public function definition(): AgentDefinition
    {
        return new AgentDefinition(
            key: 'greeting.agent',
            displayName: 'Greeting Agent',
            requiredCapabilities: ['text_generation'],
            primaryProviderProfile: 'openai-greeting',
        );
    }

    public function handle(AgentExecutionContext $context): AgentExecutionResult
    {
        return new AgentExecutionResult(
            kind: AgentExecutionResult::KIND_COMPLETE,
            output: [
            'message' => sprintf('Hello %s', (string)$context->payloadValue('name', 'friend')),
            'provider_profile' => $context->providerProfile,
          ],
            summary: 'Greeting agent completed successfully.',
        );
    }
}
