<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use CreativeCrafts\LaravelAiAgentKit\Blueprints\PromptBlueprint;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\BlueprintCompiler;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\BlueprintCompilationException;
use CreativeCrafts\LaravelAiAgentKit\Prompts\PromptExecutionMapper;

final readonly class PromptBlueprintCompiler implements BlueprintCompiler
{
    public function __construct(
        private PromptExecutionMapper $promptExecutionMapper,
    ) {
    }

    public function compile(PromptBlueprint $blueprint): ExecutionRequest
    {
        $runId = $blueprint->runId;

        if ($runId === null || $runId === '') {
            throw BlueprintCompilationException::missingRunId($blueprint->promptName);
        }

        return $this->promptExecutionMapper->mapToExecutionRequest(
            name: $blueprint->promptName,
            runId: $runId,
            variables: $blueprint->variables,
            version: $blueprint->version,
            instructions: $blueprint->instructions,
            provider: $blueprint->provider,
            model: $blueprint->model,
            toolNames: $blueprint->toolNames,
            input: $blueprint->input,
            metadata: $blueprint->metadata,
            timeout: $blueprint->timeout,
        );
    }
}
