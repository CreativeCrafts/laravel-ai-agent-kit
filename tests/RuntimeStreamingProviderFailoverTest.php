<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\StreamingAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\FailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderTargetResolver;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredFailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderTargetResolver;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\DefaultProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\RuntimeTelemetryAgent;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\SdkAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StreamComplete;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StreamFailure;
use Illuminate\Http\Client\ConnectionException;
use Laravel\Ai\Ai;
use Laravel\Ai\AiManager;
use Laravel\Ai\AiServiceProvider;

it('fails over when stream creation has a classified connection failure', function (): void {
    app()->register(AiServiceProvider::class);

    app(AiManager::class)->extend(
        'broken',
        fn (): never => throw new ConnectionException('stream connection failed'),
    );

    config()->set('ai-agent-kit.default_provider', 'broken-provider');
    config()->set('ai-agent-kit.failover_order', ['broken-provider', 'openai']);
    configureStreamingRuntimeProvider('broken-provider', 'broken', 'broken-model');
    configureStreamingRuntimeProvider('openai', 'openai', 'gpt-4o-mini');
    refreshStreamingProviderBindings();

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['Recovered stream response'])->preventStrayPrompts();

    $events = iterator_to_array(
        app(StreamingAiRuntime::class)->executeStream(
            new ExecutionRequest(
                runId: 'run-stream-creation-failover-001',
                prompt: 'Recover stream creation.',
            ),
        ),
    );

    $terminal = $events[array_key_last($events)];

    expect($terminal)
      ->toBeInstanceOf(StreamComplete::class)
      ->and($terminal->output)->toBe('Recovered stream response')
      ->and($terminal->metadata['runtime_provider_attempts'])->toBe(['broken-provider', 'openai'])
      ->and($terminal->metadata['runtime_final_provider'])->toBe('openai')
      ->and($terminal->metadata['runtime_failover_attempted'])->toBeTrue();
});

it('preserves broad stream creation failover only in explicit legacy mode', function (): void {
    app()->register(AiServiceProvider::class);

    app(AiManager::class)->extend(
        'broken',
        fn (): never => throw new RuntimeException('unknown stream creation failure'),
    );

    config()->set('ai-agent-kit.default_provider', 'broken-provider');
    config()->set('ai-agent-kit.failover_order', ['broken-provider', 'openai']);
    config()->set('ai-agent-kit.resilience.failure_classification.unknown_failure_mode', 'legacy_failover');
    configureStreamingRuntimeProvider('broken-provider', 'broken', 'broken-model');
    configureStreamingRuntimeProvider('openai', 'openai', 'gpt-4o-mini');
    refreshStreamingProviderBindings();

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['Recovered stream response'])->preventStrayPrompts();

    $events = iterator_to_array(
        app(StreamingAiRuntime::class)->executeStream(
            new ExecutionRequest(
                runId: 'run-stream-creation-legacy-failover-001',
                prompt: 'Recover stream creation in legacy mode.',
            ),
        ),
    );

    $terminal = $events[array_key_last($events)];

    expect($terminal)
      ->toBeInstanceOf(StreamComplete::class)
      ->and($terminal->output)->toBe('Recovered stream response')
      ->and($terminal->metadata['runtime_provider_attempts'])->toBe(['broken-provider', 'openai'])
      ->and($terminal->metadata['runtime_final_provider'])->toBe('openai')
      ->and($terminal->metadata['runtime_failover_attempted'])->toBeTrue();
});

it('emits one terminal stream failure and does not retry once stream iteration has started', function (): void {
    app()->register(AiServiceProvider::class);

    config()->set('ai-agent-kit.default_provider', 'openai');
    config()->set('ai-agent-kit.failover_order', ['openai', 'anthropic']);
    configureStreamingRuntimeProvider('openai', 'openai', 'gpt-4o-mini');
    configureStreamingRuntimeProvider('anthropic', 'anthropic', 'claude-3-haiku');
    refreshStreamingProviderBindings();

    $attempts = 0;
    Ai::fakeAgent(RuntimeTelemetryAgent::class, [
      static function () use (&$attempts): never {
          $attempts++;

          throw new RuntimeException('stream iteration failed');
      },
    ])->preventStrayPrompts();

    $events = iterator_to_array(
        app(StreamingAiRuntime::class)->executeStream(
            new ExecutionRequest(
                runId: 'run-stream-no-iteration-retry-001',
                prompt: 'Do not retry iterator failure.',
            ),
        ),
    );

    expect($attempts)
      ->toBe(1)
      ->and($events)->toHaveCount(1)
      ->and($events[0])->toBeInstanceOf(StreamFailure::class)
      ->and($events[0]->exceptionMessage)->toBe('stream iteration failed');
});

function configureStreamingRuntimeProvider(string $name, string $driver, string $model): void
{
    config()->set("ai-agent-kit.providers.{$name}", [
      'driver' => $driver,
      'enabled' => true,
      'capabilities' => ['text_generation'],
      'options' => ['model' => $model],
    ]);
}

function refreshStreamingProviderBindings(): void
{
    app()->forgetInstance(ConfiguredProviderRegistry::class);
    app()->forgetInstance(ProviderRegistry::class);
    app()->forgetInstance(DefaultProviderSelector::class);
    app()->forgetInstance(ProviderSelector::class);
    app()->forgetInstance(ConfiguredFailoverProviderSelector::class);
    app()->forgetInstance(FailoverProviderSelector::class);
    app()->forgetInstance(ConfiguredProviderTargetResolver::class);
    app()->forgetInstance(ProviderTargetResolver::class);
    app()->forgetInstance(SdkAiRuntime::class);
    app()->forgetInstance(AiRuntime::class);
    app()->forgetInstance(StreamingAiRuntime::class);
}
