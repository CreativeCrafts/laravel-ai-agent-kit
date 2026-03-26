<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Agents;

use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;

interface Agent
{
    public function definition(): AgentDefinition;

    public function handle(AgentExecutionContext $context): AgentExecutionResult;
}
