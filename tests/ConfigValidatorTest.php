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

it('validates redis memory configuration', function () {
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
      'memory' => [
        'default_driver' => 'redis',
        'in_memory' => [
          'retention_days' => null,
        ],
        'database' => [
          'connection' => null,
          'conversations_table' => 'ai_agent_conversations',
          'messages_table' => 'ai_agent_conversation_messages',
          'driver_name' => 'database',
          'retention_days' => 30,
          'encrypt_payloads' => true,
        ],
        'redis' => [
          'connection' => 'default',
          'prefix' => 'ai_agent_memory:',
          'driver_name' => 'redis',
          'retention_days' => 7,
        ],
      ],
    ]);

    expect(true)->toBeTrue();
});

it('validates retry resilience configuration', function () {
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
        'max_retries_per_step' => 2,
        'max_total_timeout_seconds' => 1,
        'max_tokens' => null,
        'max_cost_usd' => null,
      ],
      'resilience' => [
        'retry' => [
          'enabled' => true,
          'max_attempts' => 3,
          'backoff' => [
            'strategy' => 'exponential',
            'base_delay_ms' => 100,
            'max_delay_ms' => 500,
            'multiplier' => 2.0,
          ],
        ],
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

it('rejects an unsupported retry backoff strategy', function () {
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
        'max_retries_per_step' => 2,
        'max_total_timeout_seconds' => 1,
        'max_tokens' => null,
        'max_cost_usd' => null,
      ],
      'resilience' => [
        'retry' => [
          'enabled' => true,
          'max_attempts' => 3,
          'backoff' => [
            'strategy' => 'randomized',
            'base_delay_ms' => 100,
            'max_delay_ms' => 500,
            'multiplier' => 2.0,
          ],
        ],
      ],
    ]);
})->throws(InvalidConfigurationException::class);

it('rejects a retry backoff max delay smaller than the base delay', function () {
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
        'max_retries_per_step' => 2,
        'max_total_timeout_seconds' => 1,
        'max_tokens' => null,
        'max_cost_usd' => null,
      ],
      'resilience' => [
        'retry' => [
          'enabled' => true,
          'max_attempts' => 3,
          'backoff' => [
            'strategy' => 'linear',
            'base_delay_ms' => 500,
            'max_delay_ms' => 100,
            'multiplier' => 2.0,
          ],
        ],
      ],
    ]);
})->throws(InvalidConfigurationException::class);
