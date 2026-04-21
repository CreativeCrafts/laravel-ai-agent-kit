<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents;

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Exceptions\TextToStructuredEvaluationException;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;

final class OrchestratorRefusalAgent implements Agent
{
    public function definition(): AgentDefinition
    {
        return new AgentDefinition(
            key: 'refusal.agent',
            displayName: 'Refusal Agent',
            requiredCapabilities: ['text_generation'],
            primaryProviderProfile: 'openai-refusal',
        );
    }

    public function handle(AgentExecutionContext $context): AgentExecutionResult
    {
        throw TextToStructuredEvaluationException::refusedStructuredOutput(
            'I cannot comply with the api_token protected request.',
        );
    }
}
