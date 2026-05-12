<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\SchemaResolutionException;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use Laravel\Ai\AiServiceProvider;

it('raises SchemaResolutionException when schema class exists but does not implement HasStructuredOutput', function () {
    app()->register(AiServiceProvider::class);

    $schemaClass = new class () {};

    $className = $schemaClass::class;

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    try {
        $runtime->execute(
            new ExecutionRequest(
                runId: 'run-schema-mismatch-001',
                prompt: 'Attempt structured call.',
                provider: 'openai',
                schema: $className,
            ),
        );

        $this->fail('Expected schema resolution to fail.');
    } catch (Throwable $throwable) {
        expect($throwable->getMessage())
          ->toContain('AI runtime execution failed for run [run-schema-mismatch-001]')
          ->and($throwable->getPrevious())->toBeInstanceOf(SchemaResolutionException::class)
          ->and($throwable->getPrevious()?->getMessage())->toContain($className);
    }
});
