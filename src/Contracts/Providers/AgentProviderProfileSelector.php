<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Providers;

use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderDefinition;

interface AgentProviderProfileSelector
{
    public function selectForAgent(AgentDefinition $agentDefinition): ProviderDefinition;
}
