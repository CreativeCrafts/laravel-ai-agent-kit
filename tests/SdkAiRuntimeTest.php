<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\RuntimeExecutionException;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\SdkAiRuntime;
use Laravel\Ai\Ai;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\AnonymousAgent;

it('binds the ai runtime contract to the sdk ai runtime', function () {
    app()->register(AiServiceProvider::class);

    $runtime = app(AiRuntime::class);

    expect($runtime)->toBeInstanceOf(SdkAiRuntime::class);
});

it('executes a runtime request through the laravel ai sdk bridge', function () {
    app()->register(AiServiceProvider::class);

    Ai::fakeAgent(AnonymousAgent::class, ['Bridge response'])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $result = $runtime->execute(
        new ExecutionRequest(
            runId: 'run-bridge-001',
            prompt: 'Summarize this text.',
            instructions: ['You are a concise assistant.'],
            provider: 'openai',
            model: 'gpt-4o-mini',
            toolNames: ['math.add'],
        ),
    );

    expect($result)
      ->toBeInstanceOf(ExecutionResult::class)
      ->and($result->runId)->toBe('run-bridge-001')
      ->and($result->output)->toBe('Bridge response')
      ->and($result->provider)->toBe('openai')
      ->and($result->model)->toBe('gpt-4o-mini')
      ->and($result->usage)
      ->toHaveKey('prompt_tokens')
      ->toHaveKey('completion_tokens')
      ->and($result->metadata)
      ->toHaveKey('invocation_id')
      ->toHaveKey('requested_tool_names')
      ->and($result->metadata['requested_tool_names'])->toBe(['math.add']);
});

it('wraps sdk runtime failures in a typed runtime execution exception', function () {
    app()->register(AiServiceProvider::class);

    Ai::fakeAgent(AnonymousAgent::class, [
      static function (): never {
          throw new RuntimeException('SDK failure');
      },
    ])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    expect(fn () => $runtime->execute(
        new ExecutionRequest(
            runId: 'run-bridge-failure',
            prompt: 'Fail this request.',
            provider: 'openai',
        ),
    ))
      ->toThrow(RuntimeExecutionException::class, 'AI runtime execution failed for run [run-bridge-failure]');
});
