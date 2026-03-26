<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Agents;

use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;

interface AgentRegistry
{
    /**
     * @param class-string<Agent> $agentClass
     */
    public function register(string $agentClass): void;

    /**
     * @param iterable<class-string<Agent>> $agentClasses
     */
    public function registerMany(iterable $agentClasses): void;

    public function has(string $agentKey): bool;

    public function get(string $agentKey): Agent;

    /**
     * @return array<string, AgentDefinition>
     */
    public function all(): array;
}
