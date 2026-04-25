<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\GenerationOptions;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\RuntimeTelemetryAgent;
use Laravel\Ai\Ai;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\Contracts\HasProviderOptions;

it('routes generation options to the agent provider options map', function (): void {
    app()->register(AiServiceProvider::class);

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['Bridge response'])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $runtime->execute(
        new ExecutionRequest(
            runId: 'run-provider-options-merged',
            prompt: 'Honor my generation knobs.',
            provider: 'openai',
            generationOptions: new GenerationOptions(
                temperature: 0.2,
                maxTokens: 256,
                providerOptions: ['top_p' => 0.9],
            ),
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        $agent = $prompt->agent;

        if (!$agent instanceof HasProviderOptions) {
            return false;
        }

        $map = $agent->providerOptions('openai');

        return $map === [
            'temperature' => 0.2,
            'maxTokens' => 256,
            'top_p' => 0.9,
        ];
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
          && $agent->providerOptions('openai') === [];
    });
});

it('lets explicit providerOptions entries override typed fields on key collision when threaded through the runtime', function (): void {
    app()->register(AiServiceProvider::class);

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['Bridge response'])->preventStrayPrompts();

    /** @var AiRuntime $runtime */
    $runtime = app(AiRuntime::class);

    $runtime->execute(
        new ExecutionRequest(
            runId: 'run-provider-options-collision',
            prompt: 'Resolve collision toward explicit map.',
            provider: 'openai',
            generationOptions: new GenerationOptions(
                temperature: 0.1,
                providerOptions: ['temperature' => 0.9],
            ),
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        $agent = $prompt->agent;

        return $agent instanceof HasProviderOptions
          && ($agent->providerOptions('openai')['temperature'] ?? null) === 0.9;
    });
});
