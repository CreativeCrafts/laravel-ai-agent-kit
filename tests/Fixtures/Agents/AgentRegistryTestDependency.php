<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents;

final readonly class AgentRegistryTestDependency
{
    public function __construct(
        public string $value,
    ) {
    }
}
