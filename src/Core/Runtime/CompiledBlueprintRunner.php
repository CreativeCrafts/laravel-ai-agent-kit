<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use CreativeCrafts\LaravelAiAgentKit\Blueprints\PromptBlueprint;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\BlueprintCompiler;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\BlueprintRunner;

final readonly class CompiledBlueprintRunner implements BlueprintRunner
{
    public function __construct(
        private BlueprintCompiler $blueprintCompiler,
        private AiRuntime $aiRuntime,
    ) {
    }

    public function run(PromptBlueprint $blueprint): ExecutionResult
    {
        return $this->aiRuntime->execute($this->blueprintCompiler->compile($blueprint));
    }
}
