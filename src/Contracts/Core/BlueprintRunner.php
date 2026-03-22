<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Core;

use CreativeCrafts\LaravelAiAgentKit\Blueprints\PromptBlueprint;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;

interface BlueprintRunner
{
    public function run(PromptBlueprint $blueprint): ExecutionResult;
}
