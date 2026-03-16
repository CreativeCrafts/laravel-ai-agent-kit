<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Config Validation
    |--------------------------------------------------------------------------
    |
    | When enabled, the package validates this configuration during service
    | provider registration (fail-fast). Disable only for advanced bootstrapping
    | scenarios or highly customized test setups.
    |
    */
  'validation' => [
    'enabled' => (bool)env('AI_AGENT_KIT_VALIDATE_CONFIG', true),
  ],

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | Providers are referenced by name (array key). Each provider must declare
    | a non-empty `driver`. Provider-specific settings go into `options`.
    |
    */
  'providers' => [
    'null' => [
      'driver' => 'null',
      'enabled' => true,
      'options' => [],
    ],

      // 'openai' => [
      //     'driver' => 'openai',
      //     'enabled' => true,
      //     'options' => [
      //         // e.g. 'api_key' => env('OPENAI_API_KEY'),
      //     ],
      // ],
  ],

    /*
    |--------------------------------------------------------------------------
    | Provider Selection
    |--------------------------------------------------------------------------
    |
    | `default_provider` is the primary provider name. `failover_order` is an
    | ordered list of enabled provider names that may be attempted.
    |
    */
  'default_provider' => (string)env('AI_AGENT_KIT_DEFAULT_PROVIDER', 'null'),

  'failover_order' => array_values(
      array_filter(
          explode(',', (string)env('AI_AGENT_KIT_FAILOVER_ORDER', 'null')),
          static fn (string $name): bool => $name !== '',
      ),
  ),

    /*
    |--------------------------------------------------------------------------
    | Budgets (reserved for enforcement in later milestones)
    |--------------------------------------------------------------------------
    |
    | These keys are validated now to support fail-fast configuration safety.
    | Enforcement is implemented in later phases.
    |
    */
  'budgets' => [
    'max_steps' => (int)env('AI_AGENT_KIT_MAX_STEPS', 20),
    'max_tool_calls' => (int)env('AI_AGENT_KIT_MAX_TOOL_CALLS', 50),
    'max_retries_per_step' => (int)env('AI_AGENT_KIT_MAX_RETRIES_PER_STEP', 2),
    'max_total_timeout_seconds' => (int)env('AI_AGENT_KIT_MAX_TOTAL_TIMEOUT_SECONDS', 120),
    'max_tokens' => env('AI_AGENT_KIT_MAX_TOKENS') === null ? null : (int)env('AI_AGENT_KIT_MAX_TOKENS'),
    'max_cost_usd' => env('AI_AGENT_KIT_MAX_COST_USD') === null ? null : (float)env('AI_AGENT_KIT_MAX_COST_USD'),
  ],

    /*
    |--------------------------------------------------------------------------
    | Memory
    |--------------------------------------------------------------------------
    |
    | The in-memory driver is the default non-persistent driver and is safe for
    | tests, local development, and ephemeral runs. The database driver remains
    | available for persistent storage with encrypted payloads and retention.
    |
    */
  'memory' => [
    'default_driver' => (string)env('AI_AGENT_KIT_MEMORY_DRIVER', 'in_memory'),

    'in_memory' => [
      'retention_days' => env('AI_AGENT_KIT_MEMORY_IN_MEMORY_RETENTION_DAYS') === null
        ? null
        : (int)env('AI_AGENT_KIT_MEMORY_IN_MEMORY_RETENTION_DAYS'),
    ],

    'database' => [
      'connection' => env('AI_AGENT_KIT_MEMORY_DB_CONNECTION'),
      'conversations_table' => (string)env('AI_AGENT_KIT_MEMORY_CONVERSATIONS_TABLE', 'ai_agent_conversations'),
      'messages_table' => (string)env('AI_AGENT_KIT_MEMORY_MESSAGES_TABLE', 'ai_agent_conversation_messages'),
      'driver_name' => (string)env('AI_AGENT_KIT_MEMORY_DATABASE_DRIVER_NAME', 'database'),
      'retention_days' => env('AI_AGENT_KIT_MEMORY_RETENTION_DAYS') === null
        ? 30
        : (int)env('AI_AGENT_KIT_MEMORY_RETENTION_DAYS'),
      'encrypt_payloads' => (bool)env('AI_AGENT_KIT_MEMORY_ENCRYPT_PAYLOADS', true),
    ],
  ],
];
