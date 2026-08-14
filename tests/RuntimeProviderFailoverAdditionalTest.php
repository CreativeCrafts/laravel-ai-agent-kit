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
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\RuntimeExecutionException;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\GenerationOptions;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\RuntimeTelemetryAgent;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\SdkAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StructuredRuntimeTelemetryAgent;
use CreativeCrafts\LaravelAiAgentKit\Resilience\InMemoryCircuitBreakerManager;
use Laravel\Ai\Ai;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\Files\Base64Document;
use Illuminate\Http\Client\ConnectionException;
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
                throw new ConnectionException('primary unavailable');
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

it('uses the fallback profile model after an explicit request model fails across sdk providers', function (): void {
    app()->register(AiServiceProvider::class);

    config()->set('ai-agent-kit.default_provider', 'openai-primary');
    config()->set('ai-agent-kit.failover_order', ['openai-primary', 'anthropic-secondary']);
    configureAdditionalRuntimeProvider('openai-primary', 'openai', 'gpt-primary');
    configureAdditionalRuntimeProvider('anthropic-secondary', 'anthropic', 'claude-secondary');
    refreshAdditionalRuntimeProviderBindings();

    $attempts = 0;
    Ai::fakeAgent(RuntimeTelemetryAgent::class, [
        static function () use (&$attempts): string {
            $attempts++;

            if ($attempts === 1) {
                throw new ConnectionException('primary unavailable');
            }

            return 'Fallback response';
        },
    ])->preventStrayPrompts();

    app(AiRuntime::class)->execute(
        new ExecutionRequest(
            runId: 'run-cross-provider-model-isolation-001',
            prompt: 'Keep fallback models isolated.',
            model: 'gpt-explicit',
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, static function ($prompt): bool {
        return $prompt->provider->name() === 'anthropic'
            && $prompt->model === 'claude-secondary';
    });
});

it('uses the fallback profile model after an explicit request model fails within one sdk provider', function (): void {
    app()->register(AiServiceProvider::class);

    config()->set('ai-agent-kit.default_provider', 'openai-primary');
    config()->set('ai-agent-kit.failover_order', ['openai-primary', 'openai-secondary']);
    configureAdditionalRuntimeProvider('openai-primary', 'openai', 'gpt-primary');
    configureAdditionalRuntimeProvider('openai-secondary', 'openai', 'gpt-secondary');
    refreshAdditionalRuntimeProviderBindings();

    $attempts = 0;
    Ai::fakeAgent(RuntimeTelemetryAgent::class, [
        static function () use (&$attempts): string {
            $attempts++;

            if ($attempts === 1) {
                throw new ConnectionException('primary unavailable');
            }

            return 'Fallback response';
        },
    ])->preventStrayPrompts();

    app(AiRuntime::class)->execute(
        new ExecutionRequest(
            runId: 'run-same-provider-model-isolation-001',
            prompt: 'Use the fallback profile model.',
            model: 'gpt-explicit',
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, static function ($prompt): bool {
        return $prompt->provider->name() === 'openai'
            && $prompt->model === 'gpt-secondary';
    });
});

it('defers to the fallback sdk provider default when the fallback profile has no model', function (): void {
    app()->register(AiServiceProvider::class);

    config()->set('ai-agent-kit.default_provider', 'openai-primary');
    config()->set('ai-agent-kit.failover_order', ['openai-primary', 'anthropic-secondary']);
    configureAdditionalRuntimeProvider('openai-primary', 'openai', 'gpt-primary');
    configureAdditionalRuntimeProvider('anthropic-secondary', 'anthropic', null);
    refreshAdditionalRuntimeProviderBindings();

    $attempts = 0;
    Ai::fakeAgent(RuntimeTelemetryAgent::class, [
        static function () use (&$attempts): string {
            $attempts++;

            if ($attempts === 1) {
                throw new ConnectionException('primary unavailable');
            }

            return 'Fallback response';
        },
    ])->preventStrayPrompts();

    app(AiRuntime::class)->execute(
        new ExecutionRequest(
            runId: 'run-fallback-sdk-default-model-001',
            prompt: 'Use the fallback SDK default.',
            model: 'gpt-explicit',
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, static function ($prompt): bool {
        return $prompt->provider->name() === 'anthropic'
            && is_string($prompt->model)
            && $prompt->model !== ''
            && $prompt->model !== 'gpt-explicit';
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
                throw new ConnectionException('primary unavailable');
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

it('skips fallback profiles incompatible with an inferred structured output requirement', function (): void {
    app()->register(AiServiceProvider::class);

    config()->set('ai-agent-kit.default_provider', 'openai');
    config()->set('ai-agent-kit.failover_order', ['openai', 'text-only-anthropic', 'structured-gemini']);
    configureAdditionalRuntimeProvider('openai', 'openai', 'gpt-primary');
    configureAdditionalRuntimeProvider('text-only-anthropic', 'anthropic', 'claude-text', ['text_generation']);
    configureAdditionalRuntimeProvider('structured-gemini', 'gemini', 'gemini-structured');
    refreshAdditionalRuntimeProviderBindings();

    $attempts = 0;
    Ai::fakeAgent(StructuredRuntimeTelemetryAgent::class, [
        static function () use (&$attempts): array {
            $attempts++;

            if ($attempts === 1) {
                throw new ConnectionException('primary unavailable');
            }

            return ['ok' => true];
        },
    ])->preventStrayPrompts();

    $result = app(AiRuntime::class)->execute(
        new ExecutionRequest(
            runId: 'run-capability-aware-failover-001',
            prompt: 'Return structured output.',
            schema: static fn ($js): array => [
                'type' => 'object',
                'properties' => ['ok' => ['type' => 'boolean']],
                'required' => ['ok'],
            ],
        ),
    );

    expect($attempts)->toBe(2)
        ->and($result->metadata['runtime_provider_attempts'])->toBe(['openai', 'structured-gemini']);
});

it('preserves the original provider failure when no capability-compatible fallback exists', function (): void {
    app()->register(AiServiceProvider::class);

    config()->set('ai-agent-kit.default_provider', 'openai');
    config()->set('ai-agent-kit.failover_order', ['openai', 'text-only-anthropic']);
    configureAdditionalRuntimeProvider('openai', 'openai', 'gpt-primary');
    configureAdditionalRuntimeProvider('text-only-anthropic', 'anthropic', 'claude-text', ['text_generation']);
    refreshAdditionalRuntimeProviderBindings();

    $attempts = 0;
    Ai::fakeAgent(StructuredRuntimeTelemetryAgent::class, [
        static function () use (&$attempts): array {
            $attempts++;

            if ($attempts === 1) {
                throw new ConnectionException('original primary failure');
            }

            return ['should_not' => 'execute'];
        },
    ])->preventStrayPrompts();

    try {
        app(AiRuntime::class)->execute(
            new ExecutionRequest(
                runId: 'run-no-compatible-fallback-001',
                prompt: 'Return structured output.',
                schema: static fn ($js): array => [
                    'type' => 'object',
                    'properties' => ['ok' => ['type' => 'boolean']],
                    'required' => ['ok'],
                ],
            ),
        );
    } catch (RuntimeExecutionException $exception) {
        expect($attempts)->toBe(1)
            ->and($exception->getPrevious())->toBeInstanceOf(ConnectionException::class)
            ->and($exception->getPrevious()?->getMessage())->toBe('original primary failure');

        return;
    }

    throw new RuntimeException('Expected the original provider failure to be preserved.');
});

/**
 * @param list<string> $capabilities
 */
function configureAdditionalRuntimeProvider(
    string $name,
    string $driver,
    ?string $model,
    array $capabilities = ['text_generation', 'structured_output'],
): void {
    $options = [];

    if ($model !== null) {
        $options['model'] = $model;
    }

    config()->set("ai-agent-kit.providers.{$name}", [
        'driver' => $driver,
        'enabled' => true,
        'capabilities' => $capabilities,
        'options' => $options,
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
