<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\DefaultProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderDisabledException;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderNotDefinedException;
use Illuminate\Config\Repository;

it('binds the provider registry contract', function () {
    config()->set('ai-agent-kit.providers', [
      'null' => [
        'driver' => 'null',
        'enabled' => true,
        'capabilities' => ['text_generation'],
        'options' => ['region' => 'local'],
      ],
    ]);

    /** @var ProviderRegistry $registry */
    $registry = app(ProviderRegistry::class);

    expect($registry)
      ->toBeInstanceOf(ConfiguredProviderRegistry::class)
      ->and($registry->has('null'))->toBeTrue()
      ->and($registry->get('null')->driver)->toBe('null')
      ->and($registry->get('null')->capabilities)->toBe(['text_generation'])
      ->and($registry->get('null')->options)->toBe(['region' => 'local']);
});

it('selects the configured default provider from the container', function () {
    config()->set('ai-agent-kit.providers', [
      'null' => [
        'driver' => 'null',
        'enabled' => true,
        'capabilities' => ['text_generation'],
        'options' => ['mode' => 'sandbox'],
      ],
    ]);
    config()->set('ai-agent-kit.default_provider', 'null');

    /** @var ProviderSelector $selector */
    $selector = app(ProviderSelector::class);

    $provider = $selector->selectDefault();

    expect($selector)
      ->toBeInstanceOf(DefaultProviderSelector::class)
      ->and($provider->name)->toBe('null')
      ->and($provider->driver)->toBe('null')
      ->and($provider->enabled)->toBeTrue()
      ->and($provider->capabilities)->toBe(['text_generation'])
      ->and($provider->options)->toBe(['mode' => 'sandbox']);
});

it('throws when a requested provider is not defined', function () {
    $registry = new ConfiguredProviderRegistry(new Repository([
      'ai-agent-kit' => [
        'providers' => [
          'null' => ['driver' => 'null', 'enabled' => true, 'options' => []],
        ],
      ],
    ]));

    $registry->get('missing');
})->throws(ProviderNotDefinedException::class);

it('reflects provider configuration changes between successive reads', function () {
    $config = new Repository([
      'ai-agent-kit' => [
        'providers' => [
          'null' => ['driver' => 'null', 'enabled' => true, 'options' => []],
        ],
      ],
    ]);

    $registry = new ConfiguredProviderRegistry($config);

    expect($registry->get('null')->enabled)->toBeTrue();

    $config->set('ai-agent-kit.providers.null.enabled', false);

    expect($registry->get('null')->enabled)->toBeFalse();
});

it('throws when the configured default provider is disabled', function () {
    $config = new Repository([
      'ai-agent-kit' => [
        'default_provider' => 'null',
        'providers' => [
          'null' => ['driver' => 'null', 'enabled' => false, 'options' => []],
        ],
      ],
    ]);

    $selector = new DefaultProviderSelector(
        config: $config,
        providerRegistry: new ConfiguredProviderRegistry($config),
    );

    $selector->selectDefault();
})->throws(ProviderDisabledException::class);
