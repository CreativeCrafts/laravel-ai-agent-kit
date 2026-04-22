<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fakes;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\AgentRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use RuntimeException;

final class AgentKitTestingAgentRegistry implements AgentRegistry
{
    /**
     * @param class-string<Agent> $agentClass
     */
    public function register(string $agentClass): void
    {
    }

    /**
     * @param iterable<class-string<Agent>> $agentClasses
     */
    public function registerMany(iterable $agentClasses): void
    {
    }

    public function has(string $agentKey): bool
    {
        return true;
    }

    public function get(string $agentKey): Agent
    {
        throw new RuntimeException(sprintf('Unexpected agent lookup for [%s].', $agentKey));
    }

    /**
     * @return array<string, AgentDefinition>
     */
    public function all(): array
    {
        return [];
    }
}
