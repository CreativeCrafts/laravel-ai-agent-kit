<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\FailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderTargetResolver;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience\CircuitBreakerManager;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredFailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderTargetResolver;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\DefaultProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\RuntimeExecutionException;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\GenerationOptions;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\RuntimeTelemetryAgent;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\SdkAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StrictStructuredRuntimeTelemetryAgent;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StructuredRuntimeTelemetryAgent;
use CreativeCrafts\LaravelAiAgentKit\Resilience\InMemoryCircuitBreakerManager;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Ai;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Gateway\OpenAi\OpenAiGateway;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\JsonSchema\Types\BooleanType;
use Laravel\Ai\Contracts\Agent;

beforeEach(function (): void {
    app()->register(AiServiceProvider::class);
    config()->set('ai-agent-kit.runtime.default_instructions', []);
});

it('resolves agent kit profile names to laravel ai provider instances', function (): void {
    configureBridgeProfile(
        profile: 'scorer-primary',
        driver: 'openai',
        sdkProvider: 'openai-test',
        model: 'gpt-test-model',
    );
    refreshBridgeProviderBindings();

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['ok'])->preventStrayPrompts();

    $result = app(AiRuntime::class)->execute(
        new ExecutionRequest(
            runId: 'ak-01',
            prompt: 'Score this.',
            provider: 'scorer-primary',
        ),
    );

    expect($result->metadata['runtime_provider_attempts'])->toBe(['scorer-primary'])
      ->and($result->metadata['runtime_sdk_provider_attempts'])->toBe(['openai-test'])
      ->and($result->metadata['runtime_final_provider'])->toBe('scorer-primary')
      ->and($result->metadata['runtime_final_sdk_provider'])->toBe('openai-test');

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        return $prompt->provider->name() === 'openai-test'
          && $prompt->model === 'gpt-test-model';
    });
});

it('uses profile sdk_provider alias when configured', function (): void {
    config()->set('ai.providers.openai-underlying', [
      'driver' => 'openai',
      'key' => 'test',
    ]);
    configureBridgeProfile(
        profile: 'image-scorer',
        driver: 'openai',
        sdkProvider: 'openai-underlying',
        model: 'gpt-test',
    );
    refreshBridgeProviderBindings();

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['ok'])->preventStrayPrompts();

    $result = app(AiRuntime::class)->execute(
        new ExecutionRequest(
            runId: 'ak-02',
            prompt: 'Score the image.',
            provider: 'image-scorer',
        ),
    );

    expect($result->metadata['runtime_final_provider'])->toBe('image-scorer')
      ->and($result->metadata['runtime_final_sdk_provider'])->toBe('openai-underlying');

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        return $prompt->provider->name() === 'openai-underlying'
          && $prompt->model === 'gpt-test';
    });
});

it('resolves explicit profiles independently of failover membership', function (): void {
    config()->set('ai-agent-kit.default_provider', 'scorer-primary');
    config()->set('ai-agent-kit.failover_order', ['scorer-primary']);
    configureBridgeProfile('scorer-primary', 'openai', 'openai-primary', 'gpt-primary');
    configureBridgeProfile('scorer-secondary', 'openai', 'openai-secondary', 'gpt-secondary');
    refreshBridgeProviderBindings();

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['secondary'])->preventStrayPrompts();

    $result = app(AiRuntime::class)->execute(
        new ExecutionRequest(
            runId: 'ak-03',
            prompt: 'Use the secondary profile.',
            provider: 'scorer-secondary',
        ),
    );

    expect($result->output)->toBe('secondary')
      ->and($result->metadata['runtime_provider_attempts'])->toBe(['scorer-secondary'])
      ->and($result->metadata['runtime_sdk_provider_attempts'])->toBe(['openai-secondary'])
      ->and($result->metadata['runtime_failover_attempted'])->toBeFalse();

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        return $prompt->provider->name() === 'openai-secondary'
          && $prompt->model === 'gpt-secondary';
    });
});

it('does not enter profile failover from a direct sdk provider primary', function (): void {
    config()->set('ai-agent-kit.default_provider', 'scorer-primary');
    config()->set('ai-agent-kit.failover_order', ['scorer-primary', 'scorer-secondary']);
    configureBridgeProfile('scorer-primary', 'openai', 'openai-test', 'gpt-primary');
    configureBridgeProfile('scorer-secondary', 'anthropic', 'anthropic-test', 'claude-secondary');
    refreshBridgeProviderBindings();

    $attempts = 0;
    Ai::fakeAgent(RuntimeTelemetryAgent::class, [
      static function () use (&$attempts): string {
          $attempts++;

          throw new ConnectionException('direct provider unavailable');
      },
    ])->preventStrayPrompts();

    try {
        app(AiRuntime::class)->execute(
            new ExecutionRequest(
                runId: 'ak-direct-sdk-no-profile-failover',
                prompt: 'Use a direct SDK provider.',
                provider: 'openai-test',
                model: 'gpt-direct',
            ),
        );
    } catch (RuntimeExecutionException $exception) {
        expect($attempts)->toBe(1)
            ->and($exception->getPrevious())->toBeInstanceOf(ConnectionException::class);

        return;
    }

    throw new RuntimeException('Expected direct SDK provider failure to remain outside profile failover.');
});

it('uses the configured default profile and sdk provider when a request omits provider', function (): void {
    config()->set('ai-agent-kit.default_provider', 'scorer-primary');
    config()->set('ai-agent-kit.failover_order', ['scorer-primary']);
    configureBridgeProfile('scorer-primary', 'openai', 'openai-test', 'gpt-default-model');
    refreshBridgeProviderBindings();

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['default'])->preventStrayPrompts();

    $result = app(AiRuntime::class)->execute(
        new ExecutionRequest(
            runId: 'ak-04',
            prompt: 'Use the default profile.',
        ),
    );

    expect($result->metadata['runtime_final_provider'])->toBe('scorer-primary')
      ->and($result->metadata['runtime_final_sdk_provider'])->toBe('openai-test');

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        return $prompt->provider->name() === 'openai-test'
          && $prompt->model === 'gpt-default-model';
    });
});

it('preserves profile identity while using sdk provider identity during failover', function (): void {
    config()->set('ai-agent-kit.default_provider', 'scorer-primary');
    config()->set('ai-agent-kit.failover_order', ['scorer-primary', 'scorer-secondary']);
    configureBridgeProfile('scorer-primary', 'openai', 'openai-primary', 'gpt-primary');
    configureBridgeProfile('scorer-secondary', 'anthropic', 'anthropic-secondary', 'claude-secondary');
    refreshBridgeProviderBindings();

    $attempts = 0;
    Ai::fakeAgent(RuntimeTelemetryAgent::class, [
      static function () use (&$attempts): string {
          $attempts++;

          if ($attempts === 1) {
              throw new ConnectionException('primary unavailable');
          }

          return 'failover ok';
      },
    ])->preventStrayPrompts();

    $result = app(AiRuntime::class)->execute(
        new ExecutionRequest(
            runId: 'ak-05',
            prompt: 'Fail over with distinct identities.',
        ),
    );

    expect($attempts)->toBe(2)
      ->and($result->output)->toBe('failover ok')
      ->and($result->metadata['runtime_provider_attempts'])->toBe(['scorer-primary', 'scorer-secondary'])
      ->and($result->metadata['runtime_sdk_provider_attempts'])->toBe(['openai-primary', 'anthropic-secondary'])
      ->and($result->metadata['runtime_final_provider'])->toBe('scorer-secondary')
      ->and($result->metadata['runtime_final_sdk_provider'])->toBe('anthropic-secondary');
});

it('keys circuit breakers by profile not driver', function (): void {
    config()->set('ai-agent-kit.default_provider', 'scorer-primary');
    config()->set('ai-agent-kit.failover_order', ['scorer-primary', 'scorer-secondary']);
    config()->set('ai-agent-kit.resilience.circuit_breaker.apply_to_failover', true);
    config()->set('ai-agent-kit.resilience.circuit_breaker.failure_threshold', 1);
    configureBridgeProfile('scorer-primary', 'openai', 'openai-shared', 'gpt-a');
    configureBridgeProfile('scorer-secondary', 'openai', 'openai-shared', 'gpt-b');
    refreshBridgeProviderBindings();

    $attempts = 0;
    Ai::fakeAgent(RuntimeTelemetryAgent::class, [
      static function () use (&$attempts): string {
          $attempts++;

          if ($attempts === 1) {
              throw new ConnectionException('primary unavailable');
          }

          return 'secondary still available';
      },
    ])->preventStrayPrompts();

    $result = app(AiRuntime::class)->execute(
        new ExecutionRequest(
            runId: 'ak-06',
            prompt: 'Do not collapse independent openai profiles.',
        ),
    );

    $breakers = app(CircuitBreakerManager::class);

    expect($attempts)->toBe(2)
      ->and($result->output)->toBe('secondary still available')
      ->and($result->metadata['runtime_provider_attempts'])->toBe(['scorer-primary', 'scorer-secondary'])
      ->and($result->metadata['runtime_sdk_provider_attempts'])->toBe(['openai-shared', 'openai-shared'])
      ->and($result->metadata['runtime_final_provider'])->toBe('scorer-secondary')
      ->and($breakers->for('providers.scorer-primary')->allowsExecution())->toBeFalse()
      ->and($breakers->for('providers.scorer-secondary')->allowsExecution())->toBeTrue()
      ->and($breakers->for('providers.openai')->allowsExecution())->toBeTrue();
});

it('exposes generation max tokens through the laravel ai typed option', function (): void {
    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['ok'])->preventStrayPrompts();

    app(AiRuntime::class)->execute(
        new ExecutionRequest(
            runId: 'ak-07',
            prompt: 'Limit tokens.',
            provider: 'openai',
            generationOptions: new GenerationOptions(maxTokens: 1234),
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        $agent = $prompt->agent;

        if (!$agent instanceof RuntimeTelemetryAgent) {
            return false;
        }

        $typed = TextGenerationOptions::forAgent($agent);
        $body = openaiTextRequestBody($prompt);

        return $agent->maxTokens() === 1234
          && $typed->maxTokens === 1234
          && ($body['max_output_tokens'] ?? null) === 1234
          && !array_key_exists('maxTokens', $body)
          && !array_key_exists('maxTokens', $agent->providerOptions('openai'));
    });
});

it('exposes max steps through the laravel ai typed option', function (): void {
    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['ok'])->preventStrayPrompts();

    app(AiRuntime::class)->execute(
        new ExecutionRequest(
            runId: 'ak-08',
            prompt: 'Limit steps.',
            provider: 'openai',
            generationOptions: new GenerationOptions(maxSteps: 4),
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        $agent = $prompt->agent;

        if (!$agent instanceof RuntimeTelemetryAgent) {
            return false;
        }

        $typed = TextGenerationOptions::forAgent($agent);
        $body = openaiTextRequestBody($prompt);

        return $agent->maxSteps() === 4
          && $typed->maxSteps === 4
          && !array_key_exists('maxSteps', $body)
          && !array_key_exists('max_steps', $body)
          && !array_key_exists('maxSteps', $agent->providerOptions('openai'));
    });
});

it('exposes temperature through the laravel ai typed option', function (): void {
    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['ok'])->preventStrayPrompts();

    app(AiRuntime::class)->execute(
        new ExecutionRequest(
            runId: 'ak-09',
            prompt: 'Set temperature.',
            provider: 'openai',
            generationOptions: new GenerationOptions(temperature: 0.25),
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        $agent = $prompt->agent;

        if (!$agent instanceof RuntimeTelemetryAgent) {
            return false;
        }

        $typed = TextGenerationOptions::forAgent($agent);
        $body = openaiTextRequestBody($prompt);

        return $agent->temperature() === 0.25
          && $typed->temperature === 0.25
          && ($body['temperature'] ?? null) === 0.25
          && !array_key_exists('temperature', $agent->providerOptions('openai'));
    });
});

it('forwards scoped raw provider options on the provider-native channel', function (): void {
    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['ok'])->preventStrayPrompts();

    app(AiRuntime::class)->execute(
        new ExecutionRequest(
            runId: 'ak-10',
            prompt: 'Use reasoning effort.',
            provider: 'openai',
            generationOptions: new GenerationOptions(
                providerOptions: [
                  'openai' => [
                    'reasoning' => ['effort' => 'medium'],
                  ],
                ],
            ),
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        $agent = $prompt->agent;

        $body = openaiTextRequestBody($prompt);

        return $agent instanceof RuntimeTelemetryAgent
          && $agent->providerOptions('openai') === [
            'reasoning' => ['effort' => 'medium'],
          ]
          && ($body['reasoning'] ?? null) === ['effort' => 'medium'];
    });
});

it('does not leak provider options across failover attempts', function (): void {
    config()->set('ai-agent-kit.default_provider', 'scorer-openai');
    config()->set('ai-agent-kit.failover_order', ['scorer-openai', 'scorer-anthropic']);
    configureBridgeProfile(
        profile: 'scorer-openai',
        driver: 'openai',
        sdkProvider: 'openai',
        model: 'gpt-test',
        providerOptions: ['reasoning' => ['effort' => 'medium']],
    );
    configureBridgeProfile(
        profile: 'scorer-anthropic',
        driver: 'anthropic',
        sdkProvider: 'anthropic',
        model: 'claude-test',
        providerOptions: ['thinking' => ['budget_tokens' => 2048]],
    );
    refreshBridgeProviderBindings();

    $attempts = 0;
    Ai::fakeAgent(RuntimeTelemetryAgent::class, [
      static function () use (&$attempts): string {
          $attempts++;

          if ($attempts === 1) {
              throw new ConnectionException('openai unavailable');
          }

          return 'anthropic ok';
      },
    ])->preventStrayPrompts();

    app(AiRuntime::class)->execute(
        new ExecutionRequest(
            runId: 'ak-11',
            prompt: 'Isolate provider options.',
            generationOptions: new GenerationOptions(
                providerOptions: [
                  'openai' => ['service_tier' => 'priority'],
                  'anthropic' => ['cache_control' => ['type' => 'ephemeral']],
                ],
            ),
        ),
    );

    $anthropicOptions = null;
    $openaiOptions = null;

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt) use (&$anthropicOptions): bool {
        if (!$prompt->agent instanceof RuntimeTelemetryAgent || $prompt->provider->name() !== 'anthropic') {
            return false;
        }

        $anthropicOptions = $prompt->agent->providerOptions('anthropic');

        return true;
    });

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt) use (&$openaiOptions): bool {
        if (!$prompt->agent instanceof RuntimeTelemetryAgent || $prompt->provider->name() !== 'openai') {
            return false;
        }

        $openaiOptions = $prompt->agent->providerOptions('openai');

        return true;
    });

    expect($attempts)->toBe(2)
      ->and($openaiOptions)->toBe([
        'reasoning' => ['effort' => 'medium'],
        'service_tier' => 'priority',
      ])
      ->and($anthropicOptions)->toBe([
        'thinking' => ['budget_tokens' => 2048],
        'cache_control' => ['type' => 'ephemeral'],
      ])
      ->and($anthropicOptions)->not->toHaveKey('reasoning')
      ->and($anthropicOptions)->not->toHaveKey('service_tier');
});

it('preserves non strict structured output by default', function (): void {
    Ai::fakeAgent(StructuredRuntimeTelemetryAgent::class, [
      static fn () => new StructuredAgentResponse(
          'inv-non-strict',
          ['ok' => true],
          '{"ok":true}',
          new Usage(promptTokens: 1, completionTokens: 1),
          new Meta(provider: 'openai', model: 'gpt-4o-mini'),
      ),
    ])->preventStrayPrompts();

    app(AiRuntime::class)->execute(
        new ExecutionRequest(
            runId: 'ak-12',
            prompt: 'Return JSON.',
            provider: 'openai',
            schema: BridgeTestSchema::class,
            strictStructuredOutput: false,
        ),
    );

    Ai::assertAgentWasPrompted(StructuredRuntimeTelemetryAgent::class, function ($prompt): bool {
        $agent = $prompt->agent;

        $body = openaiTextRequestBody($prompt, ['ok' => new BooleanType()]);

        return $agent instanceof StructuredRuntimeTelemetryAgent
          && !$agent instanceof StrictStructuredRuntimeTelemetryAgent
          && (new ReflectionClass($agent))->getAttributes(Strict::class) === []
          && ($body['text']['format']['strict'] ?? null) === false;
    });
});

it('emits strict structured output when explicitly requested', function (): void {
    Ai::fakeAgent(StrictStructuredRuntimeTelemetryAgent::class, [
      static fn () => new StructuredAgentResponse(
          'inv-strict',
          ['ok' => true],
          '{"ok":true}',
          new Usage(promptTokens: 1, completionTokens: 1),
          new Meta(provider: 'openai', model: 'gpt-4o-mini'),
      ),
    ])->preventStrayPrompts();

    $request = new ExecutionRequest(
        runId: 'ak-13',
        prompt: 'Return strict JSON.',
        provider: 'openai',
        schema: BridgeTestSchema::class,
        strictStructuredOutput: true,
    );

    $cloned = $request->withMetadata(['trace' => 'yes']);

    expect($cloned->strictStructuredOutput)->toBeTrue();

    app(AiRuntime::class)->execute($cloned);

    Ai::assertAgentWasPrompted(StrictStructuredRuntimeTelemetryAgent::class, function ($prompt): bool {
        $agent = $prompt->agent;

        $body = openaiTextRequestBody($prompt, ['ok' => new BooleanType()]);

        return $agent instanceof StrictStructuredRuntimeTelemetryAgent
          && (new ReflectionClass($agent))->getAttributes(Strict::class) !== []
          && ($body['text']['format']['strict'] ?? null) === true;
    });
});

it('does not invent instructions when none were supplied', function (): void {
    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['ok'])->preventStrayPrompts();

    app(AiRuntime::class)->execute(
        new ExecutionRequest(
            runId: 'ak-14',
            prompt: 'hello',
            instructions: [],
            provider: 'openai',
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        $instructions = $prompt->agent->instructions();
        $body = openaiTextRequestBody($prompt);

        return $instructions === ''
          && !str_contains($instructions, 'Laravel AI Agent Kit runtime bridge')
          && collect($body['input'] ?? [])->doesntContain(fn (array $message): bool => ($message['role'] ?? null) === 'system');
    });
});

it('uses configured default instructions only when explicitly opted in', function (): void {
    config()->set('ai-agent-kit.runtime.default_instructions', ['You are an explicit test persona.']);
    refreshBridgeProviderBindings();

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['ok'])->preventStrayPrompts();

    app(AiRuntime::class)->execute(
        new ExecutionRequest(
            runId: 'ak-14-opt-in',
            prompt: 'hello',
            instructions: [],
            provider: 'openai',
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        return $prompt->agent->instructions() === 'You are an explicit test persona.';
    });
});

it('preserves explicit instructions exactly and in order', function (): void {
    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['ok'])->preventStrayPrompts();

    app(AiRuntime::class)->execute(
        new ExecutionRequest(
            runId: 'ak-15',
            prompt: 'hello',
            instructions: ['First rule.', 'Second rule.'],
            provider: 'openai',
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        return $prompt->agent->instructions() === "First rule.\n\nSecond rule.";
    });
});

it('preserves explicit model precedence over profile model', function (): void {
    configureBridgeProfile('scorer-primary', 'openai', 'openai-test', 'gpt-profile');
    refreshBridgeProviderBindings();

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['ok'])->preventStrayPrompts();

    app(AiRuntime::class)->execute(
        new ExecutionRequest(
            runId: 'ak-23',
            prompt: 'Override the model.',
            provider: 'scorer-primary',
            model: 'gpt-request',
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        return $prompt->model === 'gpt-request'
          && $prompt->provider->name() === 'openai-test';
    });
});

it('uses the profile model when the request omits model', function (): void {
    configureBridgeProfile('scorer-primary', 'openai', 'openai-test', 'gpt-profile');
    refreshBridgeProviderBindings();

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['ok'])->preventStrayPrompts();

    app(AiRuntime::class)->execute(
        new ExecutionRequest(
            runId: 'ak-24',
            prompt: 'Use the profile model.',
            provider: 'scorer-primary',
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        return $prompt->model === 'gpt-profile';
    });
});

it('allows the sdk model default when request and profile omit model', function (): void {
    configureBridgeProfile('scorer-primary', 'openai', 'openai-test', null);
    refreshBridgeProviderBindings();

    expect(app(ProviderTargetResolver::class)->resolve('scorer-primary')->model)->toBeNull();

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['ok'])->preventStrayPrompts();

    app(AiRuntime::class)->execute(
        new ExecutionRequest(
            runId: 'ak-25',
            prompt: 'Use the SDK default model.',
            provider: 'scorer-primary',
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        return $prompt->provider->name() === 'openai-test'
          && is_string($prompt->model)
          && $prompt->model !== '';
    });
});

it('merges profile provider options under request overrides', function (): void {
    configureBridgeProfile(
        profile: 'scorer-primary',
        driver: 'openai',
        sdkProvider: 'openai-test',
        model: 'gpt-test',
        providerOptions: [
          'reasoning' => ['effort' => 'low'],
          'service_tier' => 'default',
        ],
    );
    refreshBridgeProviderBindings();

    Ai::fakeAgent(RuntimeTelemetryAgent::class, ['ok'])->preventStrayPrompts();

    app(AiRuntime::class)->execute(
        new ExecutionRequest(
            runId: 'ak-profile-options',
            prompt: 'Merge options.',
            provider: 'scorer-primary',
            generationOptions: new GenerationOptions(
                providerOptions: [
                  'openai-test' => [
                    'reasoning' => ['effort' => 'high'],
                  ],
                ],
            ),
        ),
    );

    Ai::assertAgentWasPrompted(RuntimeTelemetryAgent::class, function ($prompt): bool {
        $agent = $prompt->agent;

        return $agent instanceof RuntimeTelemetryAgent
          && $agent->providerOptions('openai-test') === [
            'reasoning' => ['effort' => 'high'],
            'service_tier' => 'default',
          ];
    });
});

/**
 * @param array<string, mixed> $providerOptions
 */
function configureBridgeProfile(
    string $profile,
    string $driver,
    string $sdkProvider,
    ?string $model,
    array $providerOptions = [],
): void {
    $options = [];

    if ($model !== null) {
        $options['model'] = $model;
    }

    if ($providerOptions !== []) {
        $options['provider_options'] = $providerOptions;
    }

    config()->set("ai-agent-kit.providers.{$profile}", [
      'driver' => $driver,
      'sdk_provider' => $sdkProvider,
      'enabled' => true,
      'capabilities' => ['text_generation', 'structured_output', 'vision'],
      'options' => $options,
    ]);

    config()->set("ai.providers.{$sdkProvider}", [
      'driver' => $driver,
      'key' => 'test-key-for-ci',
    ]);
}

/**
 * @param array<string, mixed>|null $schema
 * @return array<string, mixed>
 */
function openaiTextRequestBody(object $prompt, ?array $schema = null): array
{
    $agent = $prompt->agent ?? null;

    if (!$agent instanceof Agent) {
        return [];
    }

    $provider = $prompt->provider ?? null;

    if (!$provider instanceof Provider) {
        return [];
    }

    $model = is_string($prompt->model ?? null) && $prompt->model !== ''
      ? $prompt->model
      : 'gpt-4o-mini';

    $gateway = new class (app(Dispatcher::class)) extends OpenAiGateway {
        /**
         * @param array<int, mixed> $messages
         * @param array<int, mixed> $tools
         * @param array<string, mixed>|null $schema
         * @return array<string, mixed>
         */
        public function capturedBody(
            Provider $provider,
            string $model,
            ?string $instructions,
            array $messages,
            array $tools,
            ?array $schema,
            ?TextGenerationOptions $options,
        ): array {
            return $this->buildTextRequestBody(
                $provider,
                $model,
                $instructions,
                $messages,
                $tools,
                $schema,
                $options,
            );
        }
    };

    $instructions = method_exists($agent, 'instructions') ? (string) $agent->instructions() : '';

    return $gateway->capturedBody(
        $provider,
        $model,
        $instructions,
        [],
        [],
        $schema,
        TextGenerationOptions::forAgent($agent),
    );
}

function refreshBridgeProviderBindings(): void
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

final class BridgeTestSchema implements HasStructuredOutput
{
    public function schema(JsonSchema $schema): array
    {
        return ['ok' => $schema->boolean()];
    }
}
