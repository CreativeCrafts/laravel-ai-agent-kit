<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fakes\AudioEvaluation;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\AgentRegistry;
use RuntimeException;

final class SchemaDrivenNoopAgentRegistry implements AgentRegistry
{
    /** @param class-string<Agent> $agentClass */
    public function register(string $agentClass): void
    {
    }

    /** @param iterable<class-string<Agent>> $agentClasses */
    public function registerMany(iterable $agentClasses): void
    {
    }

    public function has(string $agentKey): bool
    {
        return true;
    }

    public function get(string $agentKey): Agent
    {
        throw new RuntimeException('Schema-driven no-op registry does not resolve agents.');
    }

    /** @return array<string, Agent> */
    public function all(): array
    {
        return [];
    }
}
