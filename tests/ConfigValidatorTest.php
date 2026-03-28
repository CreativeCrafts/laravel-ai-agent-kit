<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Core\Config\ConfigValidator;
use CreativeCrafts\LaravelAiAgentKit\Core\Config\Exceptions\InvalidConfigurationException;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\DelegationPolicyMode;
use CreativeCrafts\LaravelAiAgentKit\Tools\DenyAllToolAuthorizer;

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

it('accepts delegation policy mode configured as enum instance', function () {
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
      'orchestration' => [
        'delegation_policy' => [
          'mode' => DelegationPolicyMode::DYNAMIC_WITH_ALLOWLIST,
        ],
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

it('validates circuit breaker resilience configuration', function () {
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
        'circuit_breaker' => [
          'enabled' => true,
          'failure_threshold' => 3,
          'reset_timeout_seconds' => 60,
          'half_open_success_threshold' => 2,
        ],
      ],
    ]);

    expect(true)->toBeTrue();
});

it('validates vector configuration', function () {
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
      'vector' => [
        'default_driver' => 'in_memory',
        'in_memory' => [
          'enabled' => true,
        ],
      ],
    ]);

    expect(true)->toBeTrue();
});

it('rejects an unsupported vector driver', function () {
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
      'vector' => [
        'default_driver' => 'unknown',
      ],
    ]);
})->throws(InvalidConfigurationException::class);

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

it('rejects an invalid circuit breaker failure threshold', function () {
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
        'circuit_breaker' => [
          'enabled' => true,
          'failure_threshold' => 0,
          'reset_timeout_seconds' => 60,
          'half_open_success_threshold' => 1,
        ],
      ],
    ]);
})->throws(InvalidConfigurationException::class, 'resilience.circuit_breaker.failure_threshold');

it('rejects an invalid circuit breaker section type', function () {
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
        'circuit_breaker' => 'invalid',
      ],
    ]);
})->throws(InvalidConfigurationException::class, 'resilience.circuit_breaker');

it('validates configured provider-native tool mappings', function () {
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
      'tools' => [
        'provider_tools' => [
          'web.search' => [
            'type' => 'web_search',
            'enabled' => true,
            'max_searches' => 3,
            'allowed_domains' => ['example.com'],
            'location' => [
              'city' => 'Stockholm',
              'region' => 'Stockholm County',
              'country' => 'SE',
            ],
          ],
          'docs.search' => [
            'type' => 'file_search',
            'enabled' => true,
            'stores' => ['store_123'],
            'filters' => ['scope' => 'support'],
          ],
        ],
      ],
    ]);

    expect(true)->toBeTrue();
});

it('rejects provider-native tool mappings with unsupported types', function () {
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
      'tools' => [
        'provider_tools' => [
          'web.search' => [
            'type' => 'browser_search',
            'enabled' => true,
          ],
        ],
      ],
    ]);
})->throws(InvalidConfigurationException::class, 'tools.provider_tools.web.search.type');

it('rejects file search provider-native tools without stores', function () {
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
      'tools' => [
        'provider_tools' => [
          'docs.search' => [
            'type' => 'file_search',
            'enabled' => true,
            'stores' => [],
          ],
        ],
      ],
    ]);
})->throws(InvalidConfigurationException::class, 'tools.provider_tools.docs.search.stores');

it('validates tool authorizer class configuration', function () {
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
      'tools' => [
        'authorizer' => DenyAllToolAuthorizer::class,
        'provider_tools' => [],
      ],
    ]);

    expect(true)->toBeTrue();
});

it('rejects tool authorizer class values that do not implement the contract', function () {
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
      'tools' => [
        'authorizer' => stdClass::class,
        'provider_tools' => [],
      ],
    ]);
})->throws(InvalidConfigurationException::class, 'tools.authorizer');

it('accepts provider capability declarations in provider configuration', function () {
    /** @var ConfigValidator $validator */
    $validator = app(ConfigValidator::class);

    $validator->validate([
      'providers' => [
        'openai-fast' => [
          'driver' => 'openai',
          'enabled' => true,
          'capabilities' => ['text_generation', 'structured_output'],
          'options' => [],
        ],
      ],
      'default_provider' => 'openai-fast',
      'failover_order' => ['openai-fast'],
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

it('rejects non-array provider capability declarations', function () {
    /** @var ConfigValidator $validator */
    $validator = app(ConfigValidator::class);

    $validator->validate([
      'providers' => [
        'openai-fast' => [
          'driver' => 'openai',
          'enabled' => true,
          'capabilities' => 'not_an_array',
          'options' => [],
        ],
      ],
      'default_provider' => 'openai-fast',
      'failover_order' => ['openai-fast'],
      'budgets' => [
        'max_steps' => 1,
        'max_tool_calls' => 1,
        'max_retries_per_step' => 1,
        'max_total_timeout_seconds' => 1,
        'max_tokens' => null,
        'max_cost_usd' => null,
      ],
    ]);
})->throws(InvalidConfigurationException::class, 'providers.openai-fast.capabilities');

it('rejects empty string capability declarations in provider configuration', function () {
    /** @var ConfigValidator $validator */
    $validator = app(ConfigValidator::class);

    $validator->validate([
      'providers' => [
        'openai-fast' => [
          'driver' => 'openai',
          'enabled' => true,
          'capabilities' => ['text_generation', ''],
          'options' => [],
        ],
      ],
      'default_provider' => 'openai-fast',
      'failover_order' => ['openai-fast'],
      'budgets' => [
        'max_steps' => 1,
        'max_tool_calls' => 1,
        'max_retries_per_step' => 1,
        'max_total_timeout_seconds' => 1,
        'max_tokens' => null,
        'max_cost_usd' => null,
      ],
    ]);
})->throws(InvalidConfigurationException::class, 'providers.openai-fast.capabilities.1');

it('rejects duplicate provider capability declarations', function () {
    /** @var ConfigValidator $validator */
    $validator = app(ConfigValidator::class);

    $validator->validate([
      'providers' => [
        'openai-fast' => [
          'driver' => 'openai',
          'enabled' => true,
          'capabilities' => ['text_generation', 'text_generation'],
          'options' => [],
        ],
      ],
      'default_provider' => 'openai-fast',
      'failover_order' => ['openai-fast'],
      'budgets' => [
        'max_steps' => 1,
        'max_tool_calls' => 1,
        'max_retries_per_step' => 1,
        'max_total_timeout_seconds' => 1,
        'max_tokens' => null,
        'max_cost_usd' => null,
      ],
    ]);
})->throws(InvalidConfigurationException::class, 'providers.openai-fast.capabilities');
