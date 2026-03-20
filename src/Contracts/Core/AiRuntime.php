<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Core;

use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;

interface AiRuntime
{
    public function execute(ExecutionRequest $request): ExecutionResult;
}
