<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderTargetResolver;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderTargetResolver;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\DefaultProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderDisabledException;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderDefinition;
use Illuminate\Config\Repository;

it('resolves agent kit profile names to laravel ai provider instances', function (): void {
    $resolver = resolverWithProviders([
      'scorer-primary' => [
        'driver' => 'openai',
        'enabled' => true,
        'options' => ['model' => 'gpt-test-model'],
      ],
    ], 'scorer-primary');

    $target = $resolver->resolve('scorer-primary');

    expect($target->profileName)->toBe('scorer-primary')
      ->and($target->sdkProviderName)->toBe('openai')
      ->and($target->driver)->toBe('openai')
      ->and($target->model)->toBe('gpt-test-model')
      ->and($target->policyIdentity())->toBe('scorer-primary');
});

it('uses profile sdk_provider alias when configured', function (): void {
    $resolver = resolverWithProviders([
      'image-scorer' => [
        'driver' => 'openai',
        'sdk_provider' => 'openai-underlying',
        'enabled' => true,
        'options' => [
          'model' => 'gpt-test',
          'provider_options' => [
            'reasoning' => ['effort' => 'medium'],
          ],
        ],
      ],
    ], 'image-scorer');

    $target = $resolver->resolve('image-scorer');

    expect($target->profileName)->toBe('image-scorer')
      ->and($target->sdkProviderName)->toBe('openai-underlying')
      ->and($target->driver)->toBe('openai')
      ->and($target->model)->toBe('gpt-test')
      ->and($target->providerOptions)->toBe([
        'reasoning' => ['effort' => 'medium'],
      ]);
});

it('resolves explicit profiles independently of failover membership', function (): void {
    $resolver = resolverWithProviders([
      'scorer-primary' => [
        'driver' => 'openai',
        'enabled' => true,
        'options' => ['model' => 'gpt-primary'],
      ],
      'scorer-secondary' => [
        'driver' => 'openai',
        'sdk_provider' => 'openai-secondary',
        'enabled' => true,
        'options' => ['model' => 'gpt-secondary'],
      ],
    ], 'scorer-primary');

    $target = $resolver->resolve('scorer-secondary');

    expect($target->profileName)->toBe('scorer-secondary')
      ->and($target->sdkProviderName)->toBe('openai-secondary')
      ->and($target->model)->toBe('gpt-secondary');
});

it('preserves explicit model precedence over profile model', function (): void {
    $resolver = resolverWithProviders([
      'scorer-primary' => [
        'driver' => 'openai',
        'enabled' => true,
        'options' => ['model' => 'gpt-profile'],
      ],
    ], 'scorer-primary');

    $target = $resolver->resolve('scorer-primary', 'gpt-request');

    expect($target->model)->toBe('gpt-request');
});

it('allows the sdk model default when request and profile omit model', function (): void {
    $resolver = resolverWithProviders([
      'scorer-primary' => [
        'driver' => 'openai',
        'enabled' => true,
        'options' => [],
      ],
    ], 'scorer-primary');

    $target = $resolver->resolve('scorer-primary');

    expect($target->model)->toBeNull();
});

it('treats unregistered names as direct laravel ai provider names', function (): void {
    $resolver = resolverWithProviders([
      'scorer-primary' => [
        'driver' => 'openai',
        'enabled' => true,
        'options' => ['model' => 'gpt-profile'],
      ],
    ], 'scorer-primary');

    $target = $resolver->resolve('openai-direct', 'gpt-direct');

    expect($target->profileName)->toBeNull()
      ->and($target->sdkProviderName)->toBe('openai-direct')
      ->and($target->driver)->toBe('openai-direct')
      ->and($target->model)->toBe('gpt-direct')
      ->and($target->providerOptions)->toBe([])
      ->and($target->policyIdentity())->toBe('openai-direct');
});

it('uses the configured default profile when the request omits a provider', function (): void {
    $resolver = resolverWithProviders([
      'scorer-primary' => [
        'driver' => 'openai',
        'sdk_provider' => 'openai-test',
        'enabled' => true,
        'options' => ['model' => 'gpt-default'],
      ],
    ], 'scorer-primary');

    $target = $resolver->resolve(null);

    expect($target->profileName)->toBe('scorer-primary')
      ->and($target->sdkProviderName)->toBe('openai-test')
      ->and($target->model)->toBe('gpt-default');
});

it('leaves modality targets unset when no provider is declared', function (): void {
    $resolver = resolverWithProviders([
      'scorer-primary' => [
        'driver' => 'openai',
        'enabled' => true,
        'options' => ['model' => 'gpt-default'],
      ],
    ], 'scorer-primary');

    $target = $resolver->resolveExplicit(null, 'whisper-request');

    expect($target->profileName)->toBeNull()
      ->and($target->sdkProviderName)->toBeNull()
      ->and($target->driver)->toBeNull()
      ->and($target->model)->toBe('whisper-request')
      ->and($target->policyIdentity())->toBe('default');
});

it('rejects an explicitly selected disabled profile', function (): void {
    $resolver = resolverWithProviders([
      'scorer-primary' => [
        'driver' => 'openai',
        'enabled' => true,
      ],
      'scorer-disabled' => [
        'driver' => 'openai',
        'sdk_provider' => 'openai-disabled',
        'enabled' => false,
      ],
    ], 'scorer-primary');

    expect(fn () => $resolver->resolve('scorer-disabled'))
      ->toThrow(ProviderDisabledException::class, 'Provider [scorer-disabled] is disabled.');

    expect(fn () => $resolver->resolveExplicit('scorer-disabled'))
      ->toThrow(ProviderDisabledException::class, 'Provider [scorer-disabled] is disabled.');
});

it('exposes known provider scope keys from profiles and laravel ai instances', function (): void {
    $resolver = resolverWithProviders([
      'scorer-primary' => [
        'driver' => 'openai',
        'sdk_provider' => 'openai-test',
        'enabled' => true,
      ],
      'scorer-secondary' => [
        'driver' => 'anthropic',
        'enabled' => true,
      ],
    ], 'scorer-primary', [
      'openai-eu' => ['driver' => 'openai'],
      'openai-us' => ['driver' => 'openai'],
    ]);

    expect($resolver->knownProviderScopeKeys())->toEqualCanonicalizing([
      'scorer-primary',
      'openai',
      'openai-test',
      'scorer-secondary',
      'anthropic',
      'openai-eu',
      'openai-us',
    ]);
});

it('builds a target from an explicit provider definition', function (): void {
    $resolver = resolverWithProviders([
      'null' => ['driver' => 'null', 'enabled' => true],
    ], 'null');

    $target = $resolver->fromDefinition(
        new ProviderDefinition(
            name: 'manual-profile',
            driver: 'openai',
            options: ['model' => 'gpt-manual', 'provider_options' => ['foo' => 'bar']],
            sdkProvider: 'openai-eu',
        ),
        'gpt-override',
    );

    expect($target->profileName)->toBe('manual-profile')
      ->and($target->sdkProviderName)->toBe('openai-eu')
      ->and($target->model)->toBe('gpt-override')
      ->and($target->providerOptions)->toBe(['foo' => 'bar']);
});

/**
 * @param array<string, array<string, mixed>> $providers
 * @param array<string, array<string, mixed>> $laravelAiProviders
 */
function resolverWithProviders(array $providers, string $default, array $laravelAiProviders = []): ProviderTargetResolver
{
    $config = new Repository([
      'ai-agent-kit' => [
        'providers' => $providers,
        'default_provider' => $default,
      ],
      'ai' => [
        'providers' => $laravelAiProviders,
      ],
    ]);

    $registry = new ConfiguredProviderRegistry($config);

    return new ConfiguredProviderTargetResolver(
        providerRegistry: $registry,
        providerSelector: new DefaultProviderSelector($config, $registry),
        config: $config,
    );
}
