<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;

final readonly class AgentRegistryTestSupportAgent implements Agent
{
    public function __construct(
        private AgentRegistryTestDependency $dependency,
    ) {
    }

    public function definition(): AgentDefinition
    {
        return new AgentDefinition(
            key: 'support.agent',
            displayName: 'Support Agent',
            requiredCapabilities: ['text_generation'],
            primaryProviderProfile: 'anthropic-support',
            fallbackProviderProfiles: ['openai-support'],
            delegationTargets: ['refund.agent'],
        );
    }

    public function handle(AgentExecutionContext $context): AgentExecutionResult
    {
        return new AgentExecutionResult(
            kind: AgentExecutionResult::KIND_COMPLETE,
            output: [
            'dependency' => $this->dependency->value,
            'task' => $context->task,
          ],
            summary: 'Support agent resolved through the container.',
        );
    }
}
