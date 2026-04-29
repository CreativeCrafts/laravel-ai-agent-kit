<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\BlueprintCompiler;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\BlueprintRunner;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Prompts\PromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\CompiledBlueprintRunner;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\MiddlewareExecutingAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\PromptBlueprintCompiler;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\RuntimeTelemetryAgent;
use CreativeCrafts\LaravelAiAgentKit\LaravelAiAgentKit;
use CreativeCrafts\LaravelAiAgentKit\Prompts\InMemoryPromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Runtime\TestRuntimeMiddlewareA;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Runtime\TestRuntimeMiddlewareB;
use Laravel\Ai\Ai;
use Laravel\Ai\AiServiceProvider;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\SdkAiRuntime;

it('wraps the sdk runtime with configured middleware for direct execute and blueprint runner', function (): void {
    app()->register(AiServiceProvider::class);

    TestRuntimeMiddlewareA::reset();

    config()->set('ai-agent-kit.runtime.middleware', [
        TestRuntimeMiddlewareA::class,
        TestRuntimeMiddlewareB::class,
    ]);

    app()->forgetInstance(SdkAiRuntime::class);
    app()->forgetInstance(AiRuntime::class);
    app()->forgetInstance(BlueprintCompiler::class);
    app()->forgetInstance(PromptBlueprintCompiler::class);
    app()->forgetInstance(CompiledBlueprintRunner::class);
    app()->forgetInstance(BlueprintRunner::class);

    app()->instance(PromptRepository::class, new InMemoryPromptRepository([
        'mw.prompt' => [
            '1.0.0' => 'Say {{word}}.',
        ],
    ]));

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['direct-response'])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    expect($runtime)->toBeInstanceOf(MiddlewareExecutingAiRuntime::class);

    $runtime->execute(new ExecutionRequest(
        runId: 'run-mw-direct',
        prompt: 'Hello',
        provider: 'openai',
        model: 'gpt-4o-mini',
    ));

    expect(TestRuntimeMiddlewareA::$log)->toBe(['A', 'B']);

    TestRuntimeMiddlewareA::reset();

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['blueprint-response'])->preventStrayPrompts();

    /** @var BlueprintRunner $runner */
    $runner = app(BlueprintRunner::class);

    $runner->run(
        LaravelAiAgentKit::prompt('mw.prompt')
            ->withRunId('run-mw-blueprint')
            ->withVersion('1.0.0')
            ->withVariables(['word' => 'hi'])
            ->usingProvider('openai')
            ->usingModel('gpt-4o-mini'),
    );

    expect(TestRuntimeMiddlewareA::$log)->toBe(['A', 'B']);
});
