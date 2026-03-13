<?php

use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\FailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredFailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderDisabledException;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderNotDefinedException;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderNotInFailoverOrderException;
use Illuminate\Config\Repository;

it('binds the failover provider selector contract', function () {
    config()->set('ai-agent-kit.providers', [
        'primary' => [
            'driver' => 'null',
            'enabled' => true,
            'options' => [],
        ],
        'secondary' => [
            'driver' => 'null',
            'enabled' => true,
            'options' => [],
        ],
    ]);
    config()->set('ai-agent-kit.failover_order', ['primary', 'secondary']);

    /** @var FailoverProviderSelector $selector */
    $selector = app(FailoverProviderSelector::class);

    expect($selector)
        ->toBeInstanceOf(ConfiguredFailoverProviderSelector::class)
        ->and(
            array_map(
                static fn ($provider): string => $provider->name,
                $selector->ordered(),
            ),
        )->toBe(['primary', 'secondary']);
});

it('returns providers in configured failover order', function () {
    $config = new Repository([
        'ai-agent-kit' => [
            'providers' => [
                'primary' => ['driver' => 'null', 'enabled' => true, 'options' => []],
                'backup' => ['driver' => 'null', 'enabled' => true, 'options' => []],
                'tertiary' => ['driver' => 'null', 'enabled' => true, 'options' => []],
            ],
            'failover_order' => ['backup', 'primary', 'tertiary'],
        ],
    ]);

    $selector = new ConfiguredFailoverProviderSelector(
        config: $config,
        providerRegistry: new ConfiguredProviderRegistry($config),
    );

    expect(
        array_map(
            static fn ($provider): string => $provider->name,
            $selector->ordered(),
        ),
    )->toBe(['backup', 'primary', 'tertiary']);
});

it('returns the next provider after the current provider', function () {
    $config = new Repository([
        'ai-agent-kit' => [
            'providers' => [
                'primary' => ['driver' => 'null', 'enabled' => true, 'options' => []],
                'backup' => ['driver' => 'null', 'enabled' => true, 'options' => []],
            ],
            'failover_order' => ['primary', 'backup'],
        ],
    ]);

    $selector = new ConfiguredFailoverProviderSelector(
        config: $config,
        providerRegistry: new ConfiguredProviderRegistry($config),
    );

    $nextProvider = $selector->nextAfter('primary');

    expect($nextProvider)->not
        ->toBeNull()
        ->and($nextProvider?->name)->toBe('backup');
});

it('returns null when the current provider is last in failover order', function () {
    $config = new Repository([
        'ai-agent-kit' => [
            'providers' => [
                'primary' => ['driver' => 'null', 'enabled' => true, 'options' => []],
                'backup' => ['driver' => 'null', 'enabled' => true, 'options' => []],
            ],
            'failover_order' => ['primary', 'backup'],
        ],
    ]);

    $selector = new ConfiguredFailoverProviderSelector(
        config: $config,
        providerRegistry: new ConfiguredProviderRegistry($config),
    );

    expect($selector->nextAfter('backup'))->toBeNull();
});

it('throws when the current provider is not in failover order', function () {
    $config = new Repository([
        'ai-agent-kit' => [
            'providers' => [
                'primary' => ['driver' => 'null', 'enabled' => true, 'options' => []],
                'backup' => ['driver' => 'null', 'enabled' => true, 'options' => []],
                'missing' => ['driver' => 'null', 'enabled' => true, 'options' => []],
            ],
            'failover_order' => ['primary', 'backup'],
        ],
    ]);

    $selector = new ConfiguredFailoverProviderSelector(
        config: $config,
        providerRegistry: new ConfiguredProviderRegistry($config),
    );

    $selector->nextAfter('missing');
})->throws(ProviderNotInFailoverOrderException::class);

it('throws when failover_order references an undefined provider', function () {
    $config = new Repository([
        'ai-agent-kit' => [
            'providers' => [
                'primary' => ['driver' => 'null', 'enabled' => true, 'options' => []],
            ],
            'failover_order' => ['primary', 'backup'],
        ],
    ]);

    $selector = new ConfiguredFailoverProviderSelector(
        config: $config,
        providerRegistry: new ConfiguredProviderRegistry($config),
    );

    $selector->ordered();
})->throws(ProviderNotDefinedException::class);

it('throws when failover_order references a disabled provider', function () {
    $config = new Repository([
        'ai-agent-kit' => [
            'providers' => [
                'primary' => ['driver' => 'null', 'enabled' => true, 'options' => []],
                'backup' => ['driver' => 'null', 'enabled' => false, 'options' => []],
            ],
            'failover_order' => ['primary', 'backup'],
        ],
    ]);

    $selector = new ConfiguredFailoverProviderSelector(
        config: $config,
        providerRegistry: new ConfiguredProviderRegistry($config),
    );

    $selector->ordered();
})->throws(ProviderDisabledException::class);
