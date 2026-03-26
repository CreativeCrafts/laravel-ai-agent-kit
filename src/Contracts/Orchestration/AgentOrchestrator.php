<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration;

use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationResult;

interface AgentOrchestrator
{
    public function run(OrchestrationRequest $request): OrchestrationResult;
}
