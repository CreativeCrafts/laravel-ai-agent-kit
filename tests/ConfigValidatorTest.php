<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Core\Config\ConfigValidator;
use CreativeCrafts\LaravelAiAgentKit\Core\Config\Exceptions\InvalidConfigurationException;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\DelegationPolicyMode;
use CreativeCrafts\LaravelAiAgentKit\Tools\DenyAllToolAuthorizer;
use CreativeCrafts\LaravelAiAgentKit\LaravelAiAgentKitServiceProvider;

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
          'allow_dynamic_delegation' => true,
        ],
      ],
    ]);

    expect(true)->toBeTrue();
});

it('rejects non-static delegation policy without explicit allow_dynamic_delegation opt-in', function () {
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
          'mode' => DelegationPolicyMode::DYNAMIC_FULL_REGISTRY->value,
        ],
      ],
    ]);
})->throws(InvalidConfigurationException::class, 'allow_dynamic_delegation');

it('rejects invalid media input URL allowlist entries', function () {
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
      'media_input' => [
        'url_allowed_hosts' => ['https://example.test'],
      ],
    ]);
})->throws(InvalidConfigurationException::class, 'media_input.url_allowed_hosts.0');

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
          'apply_to_failover' => true,
          'failure_threshold' => 3,
          'reset_timeout_seconds' => 60,
          'half_open_success_threshold' => 2,
        ],
      ],
    ]);

    expect(true)->toBeTrue();
});

it('validates prompts configuration for file and in-memory drivers', function () {
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
      'prompts' => [
        'default_driver' => 'file',
        'file' => [
          'root_path' => '/tmp/prompts',
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

it('validates database vector driver configuration', function () {
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
        'default_driver' => 'database',
        'database' => [
          'connection' => null,
          'table' => 'ai_agent_vector_documents',
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

it('rejects an invalid circuit breaker apply_to_failover value type', function () {
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
          'apply_to_failover' => 'yes',
          'failure_threshold' => 3,
          'reset_timeout_seconds' => 60,
          'half_open_success_threshold' => 1,
        ],
      ],
    ]);
})->throws(InvalidConfigurationException::class, 'resilience.circuit_breaker.apply_to_failover');

it('rejects an unsupported prompts default driver', function () {
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
      'prompts' => [
        'default_driver' => 'database',
      ],
    ]);
})->throws(InvalidConfigurationException::class, 'prompts.default_driver');

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

it('accepts web fetch provider-native tool mappings', function () {
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
          'web.fetch' => [
            'type' => 'web_fetch',
            'enabled' => true,
            'max_searches' => 2,
            'allowed_domains' => ['example.com'],
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

it('rejects invalid web search location configuration without allowed domains', function () {
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
            'location' => 'Stockholm',
          ],
        ],
      ],
    ]);
})->throws(InvalidConfigurationException::class, 'tools.provider_tools.web.search.location');

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

it('rejects invalid runtime streaming broadcast_channel type', function () {
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
        'runtime' => [
            'streaming' => [
                'broadcast_channel' => 123,
            ],
        ],
    ]);
})->throws(InvalidConfigurationException::class, 'runtime.streaming.broadcast_channel');

it('rejects modality default_driver that does not implement the modality contract', function () {
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
        'modalities' => [
            'embeddings' => [
                'default_driver' => self::class,
            ],
        ],
    ]);
})->throws(InvalidConfigurationException::class, 'modalities.embeddings.default_driver');

it('accepts memory.laravel_ai_legacy configuration', function () {
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
            'laravel_ai_legacy' => [
                'enabled' => true,
                'connection' => null,
                'conversations_table' => 'agent_conversations',
                'messages_table' => 'agent_conversation_messages',
            ],
        ],
    ]);

    expect(true)->toBeTrue();
});

it('rejects invalid memory.laravel_ai_legacy.enabled type', function () {
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
            'laravel_ai_legacy' => [
                'enabled' => 'yes',
            ],
        ],
    ]);
})->throws(InvalidConfigurationException::class, 'memory.laravel_ai_legacy.enabled');

it('accepts laravel_ai_files and laravel_ai_stores default_provider', function () {
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
        'laravel_ai_files' => [
            'default_provider' => 'openai',
        ],
        'laravel_ai_stores' => [
            'default_provider' => null,
        ],
    ]);

    expect(true)->toBeTrue();
});

it('rejects invalid laravel_ai_stores.default_provider type', function () {
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
        'laravel_ai_stores' => [
            'default_provider' => 1,
        ],
    ]);
})->throws(InvalidConfigurationException::class, 'laravel_ai_stores.default_provider');

it('accepts sdk_provider and profile provider_options', function (): void {
    /** @var ConfigValidator $validator */
    $validator = app(ConfigValidator::class);

    $validator->validate([
      'providers' => [
        'primary-image-scorer' => [
          'driver' => 'openai',
          'sdk_provider' => 'openai-production',
          'enabled' => true,
          'capabilities' => ['text_generation', 'structured_output', 'vision'],
          'options' => [
            'model' => 'gpt-example',
            'provider_options' => [
              'reasoning' => ['effort' => 'medium'],
            ],
          ],
        ],
      ],
      'default_provider' => 'primary-image-scorer',
      'failover_order' => ['primary-image-scorer'],
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

it('rejects an empty sdk_provider', function (): void {
    /** @var ConfigValidator $validator */
    $validator = app(ConfigValidator::class);

    $validator->validate([
      'providers' => [
        'openai-fast' => [
          'driver' => 'openai',
          'sdk_provider' => '',
          'enabled' => true,
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
})->throws(InvalidConfigurationException::class, 'providers.openai-fast.sdk_provider');

it('rejects non-array profile provider_options', function (): void {
    /** @var ConfigValidator $validator */
    $validator = app(ConfigValidator::class);

    $validator->validate([
      'providers' => [
        'openai-fast' => [
          'driver' => 'openai',
          'enabled' => true,
          'options' => [
            'provider_options' => 'not-an-array',
          ],
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
})->throws(InvalidConfigurationException::class, 'providers.openai-fast.options.provider_options');

it('accepts a provider profile with only options.model', function (): void {
    /** @var ConfigValidator $validator */
    $validator = app(ConfigValidator::class);

    $validator->validate(configValidatorProviderOptionsFixture([
        'model' => 'gpt-example',
    ]));

    expect(true)->toBeTrue();
});

it('accepts a provider profile with only options.provider_options', function (): void {
    /** @var ConfigValidator $validator */
    $validator = app(ConfigValidator::class);

    $validator->validate(configValidatorProviderOptionsFixture([
        'provider_options' => [
            'reasoning' => ['effort' => 'medium'],
        ],
    ]));

    expect(true)->toBeTrue();
});

it('accepts a provider profile with both supported option keys', function (): void {
    /** @var ConfigValidator $validator */
    $validator = app(ConfigValidator::class);

    $validator->validate(configValidatorProviderOptionsFixture([
        'model' => 'gpt-example',
        'provider_options' => [
            'reasoning' => ['effort' => 'medium'],
        ],
    ]));

    expect(true)->toBeTrue();
});

it('rejects unsupported provider profile option siblings', function (): void {
    /** @var ConfigValidator $validator */
    $validator = app(ConfigValidator::class);

    $validator->validate(configValidatorProviderOptionsFixture(
        [
            'reasoning_effort' => 'medium',
        ],
        'primary-image-scorer',
    ));
})->throws(
    InvalidConfigurationException::class,
    'Invalid value for config key: providers.primary-image-scorer.options.reasoning_effort. Unsupported provider profile option. Supported keys are [model, provider_options]. Provider-native options must be nested under providers.primary-image-scorer.options.provider_options.',
);

it('rejects an unknown option sibling even when supported keys are also present', function (): void {
    /** @var ConfigValidator $validator */
    $validator = app(ConfigValidator::class);

    $validator->validate(configValidatorProviderOptionsFixture([
        'model' => 'gpt-example',
        'provider_options' => [],
        'temperature' => 0.2,
    ]));
})->throws(InvalidConfigurationException::class, 'providers.primary-image-scorer.options.temperature');

it('accepts the same key when nested under provider_options', function (): void {
    /** @var ConfigValidator $validator */
    $validator = app(ConfigValidator::class);

    $validator->validate(configValidatorProviderOptionsFixture([
        'provider_options' => [
            'reasoning_effort' => 'medium',
            'future_native_option' => ['enabled' => true],
        ],
    ]));

    expect(true)->toBeTrue();
});

it('rejects numeric provider profile option keys', function (): void {
    /** @var ConfigValidator $validator */
    $validator = app(ConfigValidator::class);

    $validator->validate(configValidatorProviderOptionsFixture([
        0 => 'model',
    ]));
})->throws(InvalidConfigurationException::class, 'providers.primary-image-scorer.options.0');

it('rejects empty nested provider_options keys', function (): void {
    /** @var ConfigValidator $validator */
    $validator = app(ConfigValidator::class);

    $validator->validate(configValidatorProviderOptionsFixture([
        'provider_options' => [
            '' => 'value',
        ],
    ]));
})->throws(InvalidConfigurationException::class, 'providers.primary-image-scorer.options.provider_options');

it('rejects non-string nested provider_options keys', function (): void {
    /** @var ConfigValidator $validator */
    $validator = app(ConfigValidator::class);

    $validator->validate(configValidatorProviderOptionsFixture([
        'provider_options' => [
            0 => 'value',
        ],
    ]));
})->throws(InvalidConfigurationException::class, 'Keys must be non-empty strings.');

it('skips current-config validation at boot when validation is disabled', function (): void {
    config()->set('ai-agent-kit.providers.null.options', [
        'reasoning_effort' => 'medium',
    ]);

    $provider = app()->getProvider(LaravelAiAgentKitServiceProvider::class);

    expect($provider)->toBeInstanceOf(LaravelAiAgentKitServiceProvider::class);

    expect(fn () => $provider->packageBooted())
        ->toThrow(InvalidConfigurationException::class, 'providers.null.options.reasoning_effort');

    config()->set('ai-agent-kit.validation.enabled', false);

    $provider->packageBooted();

    expect(config('ai-agent-kit.providers.null.options.reasoning_effort'))->toBe('medium');
});

/**
 * @param array<int|string, mixed> $options
 * @return array<string, mixed>
 */
function configValidatorProviderOptionsFixture(array $options, string $profile = 'primary-image-scorer'): array
{
    return [
        'providers' => [
            $profile => [
                'driver' => 'openai',
                'enabled' => true,
                'options' => $options,
            ],
        ],
        'default_provider' => $profile,
        'failover_order' => [$profile],
        'budgets' => [
            'max_steps' => 1,
            'max_tool_calls' => 1,
            'max_retries_per_step' => 1,
            'max_total_timeout_seconds' => 1,
            'max_tokens' => null,
            'max_cost_usd' => null,
        ],
    ];
}
