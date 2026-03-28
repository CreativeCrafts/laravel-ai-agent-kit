<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;

final class OrchestratorFailingAgent implements Agent
{
    public function definition(): AgentDefinition
    {
        return new AgentDefinition(
            key: 'failing.agent',
            displayName: 'Failing Agent',
            requiredCapabilities: ['text_generation'],
            primaryProviderProfile: 'openai-greeting',
        );
    }

    public function handle(AgentExecutionContext $context): AgentExecutionResult
    {
        return new AgentExecutionResult(
            kind: AgentExecutionResult::KIND_FAIL,
            output: [
            'reason_code' => 'provider_declined',
            'requested_by' => $context->payloadValue('requester', 'unknown'),
          ],
            summary: 'Failing agent could not process api_token protected work.',
        );
    }
}
