<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;

final class AgentRegistryTestDuplicateSupportAgent implements Agent
{
    public function definition(): AgentDefinition
    {
        return new AgentDefinition(
            key: 'support.agent',
            displayName: 'Duplicate Support Agent',
            requiredCapabilities: ['text_generation'],
            primaryProviderProfile: 'anthropic-support',
        );
    }

    public function handle(AgentExecutionContext $context): AgentExecutionResult
    {
        return new AgentExecutionResult(
            kind: AgentExecutionResult::KIND_COMPLETE,
            output: ['task' => $context->task],
            summary: 'Duplicate agent should never be used.',
        );
    }
}
