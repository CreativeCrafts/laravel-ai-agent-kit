<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Core\Config\ConfigValidator;
use CreativeCrafts\LaravelAiAgentKit\Core\Config\Exceptions\InvalidConfigurationException;

it('validates a minimal configuration', function () {
    /** @var ConfigValidator $validator */
    $validator = app(ConfigValidator::class);

    $validator->validate([
        'providers' => [
            'null' => [
                'driver' => 'null',
                'enabled' => true,
                'options' => [],
            ],
        ],
        'default_provider' => 'null',
        'failover_order' => ['null'],
        'budgets' => [
            'max_steps' => 1,
            'max_tool_calls' => 1,
            'max_retries_per_step' => 1,
            'max_total_timeout_seconds' => 1,
            'max_tokens' => null,
            'max_cost_usd' => null,
        ],
    ]);

    expect(true)->toBeTrue();
});

it('rejects missing providers', function () {
    /** @var ConfigValidator $validator */
    $validator = app(ConfigValidator::class);

    $validator->validate([
        'default_provider' => 'null',
        'failover_order' => ['null'],
    ]);
})->throws(InvalidConfigurationException::class);

it('rejects an unknown default provider', function () {
    /** @var ConfigValidator $validator */
    $validator = app(ConfigValidator::class);

    $validator->validate([
        'providers' => [
            'a' => ['driver' => 'null', 'enabled' => true],
        ],
        'default_provider' => 'b',
        'failover_order' => ['a'],
    ]);
})->throws(InvalidConfigurationException::class);

it('rejects duplicate entries in failover_order', function () {
    /** @var ConfigValidator $validator */
    $validator = app(ConfigValidator::class);

    $validator->validate([
        'providers' => [
            'a' => ['driver' => 'null', 'enabled' => true],
        ],
        'default_provider' => 'a',
        'failover_order' => ['a', 'a'],
    ]);
})->throws(InvalidConfigurationException::class);
