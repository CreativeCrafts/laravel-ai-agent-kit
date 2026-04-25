<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\SchemaResolutionException;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use Laravel\Ai\AiServiceProvider;

it('raises SchemaResolutionException when schema class exists but does not implement HasStructuredOutput', function () {
    app()->register(AiServiceProvider::class);

    $schemaClass = new class () {
    };

    $className = $schemaClass::class;

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    expect(fn () => $runtime->execute(
        new ExecutionRequest(
            runId: 'run-schema-mismatch-001',
            prompt: 'Attempt structured call.',
            provider: 'openai',
            schema: $className,
        ),
    ))->toThrow(SchemaResolutionException::class, $className);
});
