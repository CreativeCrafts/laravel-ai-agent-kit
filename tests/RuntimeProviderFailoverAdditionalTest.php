<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\FailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience\CircuitBreakerManager;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredFailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\DefaultProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\GenerationOptions;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\RuntimeTelemetryAgent;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\SdkAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StructuredRuntimeTelemetryAgent;
use CreativeCrafts\LaravelAiAgentKit\Resilience\InMemoryCircuitBreakerManager;
use Laravel\Ai\Ai;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\Files\Base64Document;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderTargetResolver;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderTargetResolver;

it('preserves structured schema attachments generation options and timeout across prompt failover attempts', function (): void {
    app()->register(AiServiceProvider::class);

    config()->set('ai-agent-kit.default_provider', 'openai');
    config()->set('ai-agent-kit.failover_order', ['openai', 'anthropic']);
    configureAdditionalRuntimeProvider('openai', 'openai', 'gpt-4o-mini');
    configureAdditionalRuntimeProvider('anthropic', 'anthropic', 'claude-3-haiku');
    refreshAdditionalRuntimeProviderBindings();

    $attempts = 0;
    Ai::fakeAgent(StructuredRuntimeTelemetryAgent::class, [
        static function () use (&$attempts): array {
            $attempts++;

            if ($attempts === 1) {
                throw new RuntimeException('primary unavailable');
            }

            return ['ok' => true];
        },
    ])->preventStrayPrompts();

    $attachment = new Base64Document(base64_encode('hello'), 'text/plain');

    $result = app(AiRuntime::class)->execute(
        new ExecutionRequest(
            runId: 'run-preserve-failover-state-001',
            prompt: 'Use preserved request state.',
            timeout: 17,
            generationOptions: new GenerationOptions(temperature: 0.4, maxTokens: 123),
            schema: static fn ($js): array => [
                'type' => 'object',
                'properties' => ['ok' => ['type' => 'boolean']],
                'required' => ['ok'],
            ],
            attachments: [$attachment],
        ),
    );

    expect($attempts)->toBe(2)
        ->and($result->metadata['runtime_provider_attempts'])->toBe(['openai', 'anthropic'])
        ->and($result->metadata['runtime_final_provider'])->toBe('anthropic');

    Ai::assertAgentWasPrompted(StructuredRuntimeTelemetryAgent::class, function ($prompt): bool {
        $options = $prompt->agent instanceof StructuredRuntimeTelemetryAgent
            ? $prompt->agent->providerOptions('anthropic')
            : [];

        return $prompt->provider->name() === 'anthropic'
            && $prompt->model === 'claude-3-haiku'
            && $prompt->timeout === 17
            && $prompt->attachments->count() === 1
            && $prompt->agent instanceof StructuredRuntimeTelemetryAgent
            && $prompt->agent->temperature() === 0.4
            && $prompt->agent->maxTokens() === 123
            && $options === [];
    });
});

it('skips open circuit breaker providers during runtime failover', function (): void {
    app()->register(AiServiceProvider::class);

    config()->set('ai-agent-kit.default_provider', 'openai');
    config()->set('ai-agent-kit.failover_order', ['openai', 'anthropic', 'gemini']);
    config()->set('ai-agent-kit.resilience.circuit_breaker.apply_to_failover', true);
    config()->set('ai-agent-kit.resilience.circuit_breaker.failure_threshold', 1);
    configureAdditionalRuntimeProvider('openai', 'openai', 'gpt-4o-mini');
    configureAdditionalRuntimeProvider('anthropic', 'anthropic', 'claude-3-haiku');
    configureAdditionalRuntimeProvider('gemini', 'gemini', 'gemini-2.0-flash');
    refreshAdditionalRuntimeProviderBindings();

    app(CircuitBreakerManager::class)->for('providers.anthropic')->recordFailure();

    $attempts = 0;
    Ai::fakeAgent(RuntimeTelemetryAgent::class, [
        static function () use (&$attempts): string {
            $attempts++;

            if ($attempts === 1) {
                throw new RuntimeException('primary unavailable');
            }

            return 'Gemini fallback response';
        },
    ])->preventStrayPrompts();

    $result = app(AiRuntime::class)->execute(
        new ExecutionRequest(
            runId: 'run-skip-open-breaker-001',
            prompt: 'Skip open breakers.',
        ),
    );

    expect($attempts)->toBe(2)
        ->and($result->output)->toBe('Gemini fallback response')
        ->and($result->metadata['runtime_provider_attempts'])->toBe(['openai', 'gemini'])
        ->and($result->metadata['runtime_final_provider'])->toBe('gemini');
});

function configureAdditionalRuntimeProvider(string $name, string $driver, string $model): void
{
    config()->set("ai-agent-kit.providers.{$name}", [
        'driver' => $driver,
        'enabled' => true,
        'capabilities' => ['text_generation', 'structured_output'],
        'options' => ['model' => $model],
    ]);
}

function refreshAdditionalRuntimeProviderBindings(): void
{
    app()->forgetInstance(ConfiguredProviderRegistry::class);
    app()->forgetInstance(ProviderRegistry::class);
    app()->forgetInstance(DefaultProviderSelector::class);
    app()->forgetInstance(ProviderSelector::class);
    app()->forgetInstance(ConfiguredFailoverProviderSelector::class);
    app()->forgetInstance(FailoverProviderSelector::class);
    app()->forgetInstance(ConfiguredProviderTargetResolver::class);
    app()->forgetInstance(ProviderTargetResolver::class);
    app()->forgetInstance(InMemoryCircuitBreakerManager::class);
    app()->forgetInstance(CircuitBreakerManager::class);
    app()->forgetInstance(SdkAiRuntime::class);
    app()->forgetInstance(AiRuntime::class);
}
