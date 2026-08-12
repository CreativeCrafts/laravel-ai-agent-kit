<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\GenerationOptions;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\RuntimeTelemetryAgent;
use Laravel\Ai\Ai;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Gateway\TextGenerationOptions;

it('exposes typed generation options through laravel ai agent methods not provider options', function (): void {
    app()->register(AiServiceProvider::class);

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['Bridge response'])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $runtime->execute(
        new ExecutionRequest(
            runId: 'run-provider-options-typed',
            prompt: 'Honor my generation knobs.',
            provider: 'openai',
            generationOptions: new GenerationOptions(
                temperature: 0.2,
                maxTokens: 256,
                maxSteps: 3,
                providerOptions: ['top_p' => 0.9],
            ),
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        $agent = $prompt->agent;

        if (!$agent instanceof HasProviderOptions || !$agent instanceof RuntimeTelemetryAgent) {
            return false;
        }

        $typed = TextGenerationOptions::forAgent($agent);

        return $agent->maxTokens() === 256
          && $agent->maxSteps() === 3
          && $agent->temperature() === 0.2
          && $typed->maxTokens === 256
          && $typed->maxSteps === 3
          && $typed->temperature === 0.2
          && $agent->providerOptions('openai') === ['top_p' => 0.9]
          && !array_key_exists('maxTokens', $agent->providerOptions('openai'))
          && !array_key_exists('maxSteps', $agent->providerOptions('openai'))
          && !array_key_exists('temperature', $agent->providerOptions('openai'));
    });
});

it('returns an empty provider options map when no generation options are supplied', function (): void {
    app()->register(AiServiceProvider::class);

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['Bridge response'])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $runtime->execute(
        new ExecutionRequest(
            runId: 'run-provider-options-defaults',
            prompt: 'No generation options here.',
            provider: 'openai',
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        $agent = $prompt->agent;

        return $agent instanceof HasProviderOptions
          && $agent instanceof RuntimeTelemetryAgent
          && $agent->providerOptions('openai') === []
          && $agent->maxTokens() === null
          && $agent->maxSteps() === null
          && $agent->temperature() === null;
    });
});

it('forwards unscoped raw provider options without mixing typed fields', function (): void {
    app()->register(AiServiceProvider::class);

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['Bridge response'])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $runtime->execute(
        new ExecutionRequest(
            runId: 'run-provider-options-raw',
            prompt: 'Keep typed temperature separate from raw options.',
            provider: 'openai',
            generationOptions: new GenerationOptions(
                temperature: 0.1,
                providerOptions: ['top_p' => 0.9],
            ),
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        $agent = $prompt->agent;

        return $agent instanceof HasProviderOptions
          && $agent instanceof RuntimeTelemetryAgent
          && $agent->temperature() === 0.1
          && $agent->providerOptions('openai') === ['top_p' => 0.9];
    });
});
