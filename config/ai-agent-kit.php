<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Tools\DenyAllToolAuthorizer;

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
    | Ephemeral driver warnings (optional)
    |--------------------------------------------------------------------------
    |
    | When enabled, the package logs a single warning per PHP process if an
    | in-memory driver is selected while the application environment matches
    | `environments` (default: production). Does not throw.
    |
    */
  'ephemeral_driver_warnings' => [
    'enabled' => (bool)env('AI_AGENT_KIT_EPHEMERAL_DRIVER_WARNINGS', false),
    'environments' => array_values(
        array_filter(
            explode(',', (string)env('AI_AGENT_KIT_EPHEMERAL_WARN_ENVIRONMENTS', 'production')),
            static fn (string $name): bool => $name !== '',
        ),
    ),
  ],

    /*
    |--------------------------------------------------------------------------
    | Queued pipeline
    |--------------------------------------------------------------------------
    |
    | Optional dev guard: when `debug_payload_guard` is true and `config(app.debug)`
    | is true, dispatch fails if the serialized queued job exceeds
    | `max_serialized_job_bytes`. See UPGRADE.md for RunContext payload hygiene.
    |
    */
  'pipeline' => [
    'queued' => [
      'debug_payload_guard' => (bool)env('AI_AGENT_KIT_QUEUED_PIPELINE_DEBUG_PAYLOAD_GUARD', false),
      'max_serialized_job_bytes' => env('AI_AGENT_KIT_QUEUED_PIPELINE_MAX_SERIALIZED_BYTES') === null
        ? 524288
        : (int)env('AI_AGENT_KIT_QUEUED_PIPELINE_MAX_SERIALIZED_BYTES'),
    ],
  ],

    /*
    |--------------------------------------------------------------------------
    | Observability
    |--------------------------------------------------------------------------
    |
    | Redacted package events for Laravel AI Files / Stores gateway calls made
    | through `LaravelAiFilesService` and `LaravelAiStoresService`. Disable in
    | tests if you assert event counts globally.
    |
    */
  'observability' => [
    'laravel_ai_files_stores' => [
      'enabled' => (bool)env('AI_AGENT_KIT_LARAVEL_AI_FILES_STORES_OBSERVABILITY', true),
    ],
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
      'capabilities' => [],
      'options' => [],
    ],

      // 'openai-fast' => [
      //     'driver' => 'openai',
      //     'enabled' => true,
      //     'capabilities' => ['text_generation', 'structured_output'],
      //     'options' => [
      //         // e.g. 'model' => 'gpt-4o-mini',
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
    | Budgets
    |--------------------------------------------------------------------------
    |
    | These values remain the global execution ceilings for later orchestration
    | milestones. The retry ceiling is also used now to bound the resolved retry
    | policy so retry configuration stays explicit and cost-safe.
    |
    */
  'budgets' => [
    'max_steps' => (int)env('AI_AGENT_KIT_MAX_STEPS', 20),
    'max_orchestration_depth' => (int)env('AI_AGENT_KIT_MAX_ORCHESTRATION_DEPTH', 25),
    'max_tool_calls' => (int)env('AI_AGENT_KIT_MAX_TOOL_CALLS', 50),
    'max_retries_per_step' => (int)env('AI_AGENT_KIT_MAX_RETRIES_PER_STEP', 2),
    'max_total_timeout_seconds' => (int)env('AI_AGENT_KIT_MAX_TOTAL_TIMEOUT_SECONDS', 120),
    'max_tokens' => env('AI_AGENT_KIT_MAX_TOKENS') === null ? null : (int)env('AI_AGENT_KIT_MAX_TOKENS'),
    'max_cost_usd' => env('AI_AGENT_KIT_MAX_COST_USD') === null ? null : (float)env('AI_AGENT_KIT_MAX_COST_USD'),
  ],

    /*
    |--------------------------------------------------------------------------
    | Orchestration
    |--------------------------------------------------------------------------
    |
    | Delegation policy controls how agent handoffs are approved. Static mode
    | honors only each agent's declared delegation targets. Dynamic modes can
    | expand allowed targets with explicit allowlists or full registry access.
    |
    */
  'orchestration' => [
    'delegation_policy' => [
      'mode' => (string)env('AI_AGENT_KIT_ORCHESTRATION_DELEGATION_POLICY_MODE', 'static_only'),
      'allowlist' => [],
      'rewrites' => [],
    ],
  ],

    /*
    |--------------------------------------------------------------------------
    | Resilience
    |--------------------------------------------------------------------------
    |
    | Retry policies are declared explicitly and later consumed by pipeline
    | runners. The resolved retry policy is always bounded by `budgets`
    | `max_retries_per_step` so configuration cannot bypass global ceilings.
    |
    */
  'resilience' => [
    'retry' => [
      'enabled' => (bool)env('AI_AGENT_KIT_RETRY_ENABLED', true),
      'max_attempts' => (int)env('AI_AGENT_KIT_RETRY_MAX_ATTEMPTS', 3),
      'backoff' => [
        'strategy' => (string)env('AI_AGENT_KIT_RETRY_BACKOFF_STRATEGY', 'exponential'),
        'base_delay_ms' => (int)env('AI_AGENT_KIT_RETRY_BACKOFF_BASE_DELAY_MS', 250),
        'max_delay_ms' => (int)env('AI_AGENT_KIT_RETRY_BACKOFF_MAX_DELAY_MS', 2000),
        'multiplier' => env('AI_AGENT_KIT_RETRY_BACKOFF_MULTIPLIER') === null
          ? 2.0
          : (float)env('AI_AGENT_KIT_RETRY_BACKOFF_MULTIPLIER'),
      ],
    ],
    'circuit_breaker' => [
      'enabled' => (bool)env('AI_AGENT_KIT_CIRCUIT_BREAKER_ENABLED', true),
      'apply_to_failover' => (bool)env('AI_AGENT_KIT_CIRCUIT_BREAKER_APPLY_TO_FAILOVER', false),
      'failure_threshold' => (int)env('AI_AGENT_KIT_CIRCUIT_BREAKER_FAILURE_THRESHOLD', 3),
      'reset_timeout_seconds' => (int)env('AI_AGENT_KIT_CIRCUIT_BREAKER_RESET_TIMEOUT_SECONDS', 60),
      'half_open_success_threshold' => (int)env('AI_AGENT_KIT_CIRCUIT_BREAKER_HALF_OPEN_SUCCESS_THRESHOLD', 1),
    ],
  ],

    /*
    |--------------------------------------------------------------------------
    | Prompt Repository
    |--------------------------------------------------------------------------
    |
    | Prompts can be resolved from in-memory registrations or loaded from the
    | filesystem (compatible with ai:make:prompt scaffolds).
    |
    */
  'prompts' => [
    'default_driver' => (string)env('AI_AGENT_KIT_PROMPTS_DRIVER', 'in_memory'),

    'file' => [
      'root_path' => env('AI_AGENT_KIT_PROMPTS_FILE_ROOT_PATH'),
    ],
  ],

    /*
    |--------------------------------------------------------------------------
    | Memory
    |--------------------------------------------------------------------------
    |
    | The in-memory driver is the default non-persistent driver and is safe for
    | tests, local development, and ephemeral runs. The database driver remains
    | available for persistent storage with encrypted payloads and retention.
    | The Redis driver supports shared ephemeral memory across workers.
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

    'redis' => [
      'connection' => env('AI_AGENT_KIT_MEMORY_REDIS_CONNECTION'),
      'prefix' => (string)env('AI_AGENT_KIT_MEMORY_REDIS_PREFIX', 'ai_agent_memory:'),
      'driver_name' => (string)env('AI_AGENT_KIT_MEMORY_REDIS_DRIVER_NAME', 'redis'),
      'retention_days' => env('AI_AGENT_KIT_MEMORY_REDIS_RETENTION_DAYS') === null
        ? null
        : (int)env('AI_AGENT_KIT_MEMORY_REDIS_RETENTION_DAYS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Laravel AI legacy conversation tables (read bridge)
    |--------------------------------------------------------------------------
    |
    | When `memory.default_driver` is `database`, enabling this option wraps the
    | package store so `find()` falls back to Laravel AI's default
    | `agent_conversations` / `agent_conversation_messages` rows when no matching
    | `ai_agent_*` record exists. Writes always use the package tables.
    |
    */
    'laravel_ai_legacy' => [
      'enabled' => (bool)env('AI_AGENT_KIT_MEMORY_LARAVEL_AI_LEGACY_FALLBACK', false),
      'connection' => env('AI_AGENT_KIT_MEMORY_LARAVEL_AI_LEGACY_CONNECTION'),
      'conversations_table' => (string)env(
          'AI_AGENT_KIT_MEMORY_LARAVEL_AI_LEGACY_CONVERSATIONS_TABLE',
          'agent_conversations',
      ),
            'messages_table' => (string)env(
                'AI_AGENT_KIT_MEMORY_LARAVEL_AI_LEGACY_MESSAGES_TABLE',
                'agent_conversation_messages',
            ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversation attachment replay (Phase 6)
    |--------------------------------------------------------------------------
    |
    | When enabled, continuing a conversation can rehydrate persisted attachment
    | payloads from the previous user turn according to policy. Opt in per
    | request with ExecutionRequest metadata `attachment_replay` = `merge` or
    | `replay_only` (default `none`).
    |
    */
    'attachments_replay' => [
      'enabled' => (bool)env('AI_AGENT_KIT_MEMORY_ATTACHMENTS_REPLAY_ENABLED', false),
      'max_per_turn' => env('AI_AGENT_KIT_MEMORY_ATTACHMENTS_REPLAY_MAX_PER_TURN') === null
        ? null
        : (int)env('AI_AGENT_KIT_MEMORY_ATTACHMENTS_REPLAY_MAX_PER_TURN'),
      'max_age_seconds' => env('AI_AGENT_KIT_MEMORY_ATTACHMENTS_REPLAY_MAX_AGE_SECONDS') === null
        ? null
        : (int)env('AI_AGENT_KIT_MEMORY_ATTACHMENTS_REPLAY_MAX_AGE_SECONDS'),
      'allow_provider_references' => (bool)env('AI_AGENT_KIT_MEMORY_ATTACHMENTS_REPLAY_ALLOW_PROVIDER_REFERENCES', true),
      'deny_types' => [
        'base64-image',
        'base64-document',
        'base64-audio',
        'local-image',
        'local-document',
        'local-audio',
      ],
      'deny_url_substrings' => [],
    ],
  ],

    /*
    |--------------------------------------------------------------------------
    | Vector Store
    |--------------------------------------------------------------------------
    |
    | `in_memory` is for local use and tests. `database` persists vectors in
    | `ai_agent_vector_documents` (publish migrations). Use a custom binding
    | when you need an external index (Pinecone, etc.).
    |
    */
  'vector' => [
    'default_driver' => (string)env('AI_AGENT_KIT_VECTOR_DRIVER', 'in_memory'),

    'in_memory' => [
      'enabled' => (bool)env('AI_AGENT_KIT_VECTOR_IN_MEMORY_ENABLED', true),
    ],

    'database' => [
      'connection' => env('AI_AGENT_KIT_VECTOR_DATABASE_CONNECTION'),
      'table' => (string)env('AI_AGENT_KIT_VECTOR_DATABASE_TABLE', 'ai_agent_vector_documents'),
      'max_scan_rows' => env('AI_AGENT_KIT_VECTOR_DATABASE_MAX_SCAN_ROWS') === null
        ? null
        : (int)env('AI_AGENT_KIT_VECTOR_DATABASE_MAX_SCAN_ROWS'),
    ],
  ],

    /*
    |--------------------------------------------------------------------------
    | Laravel AI Files / Stores (provider APIs)
    |--------------------------------------------------------------------------
    |
    | Optional default Laravel AI provider keys for package facades over
    | `Laravel\Ai\Files` and `Laravel\Ai\Stores`. Null defers to Laravel AI's
    | per-call behavior. See UPGRADE.md.
    |
    */
  'laravel_ai_files' => [
    'default_provider' => env('AI_AGENT_KIT_LARAVEL_AI_FILES_PROVIDER'),
  ],

  'laravel_ai_stores' => [
    'default_provider' => env('AI_AGENT_KIT_LARAVEL_AI_STORES_PROVIDER'),
  ],

    /*
    |--------------------------------------------------------------------------
    | Tool Materialization
    |--------------------------------------------------------------------------
    |
    | Package tools remain explicit and package-owned. Provider-native tools
    | may only be enabled here through explicit aliases that the runtime can
    | materialize on demand when a request names them.
    |
    */
  'tools' => [
    'authorizer' => DenyAllToolAuthorizer::class,

    /*
    |--------------------------------------------------------------------------
    | Similarity search (VectorStoreInterface + embeddings)
    |--------------------------------------------------------------------------
    |
    | Registers the package `similarity_search` custom tool when enabled.
    | Embeds the query via `EmbeddingsRuntime`, then searches `VectorStoreInterface`.
    | Authorization still flows through `tools.authorizer` (default: deny all).
    |
    */
    'similarity_search' => [
      'enabled' => (bool)env('AI_AGENT_KIT_SIMILARITY_SEARCH_ENABLED', false),
      'register' => (bool)env('AI_AGENT_KIT_SIMILARITY_SEARCH_REGISTER', false),
      'name' => env('AI_AGENT_KIT_SIMILARITY_SEARCH_NAME', 'similarity_search'),
      'default_namespace' => (string)env('AI_AGENT_KIT_SIMILARITY_SEARCH_NAMESPACE', 'default'),
      'default_limit' => (int)env('AI_AGENT_KIT_SIMILARITY_SEARCH_LIMIT', 10),
      'embedding_dimensions' => env('AI_AGENT_KIT_SIMILARITY_SEARCH_EMBEDDING_DIMENSIONS') !== null
        ? (int)env('AI_AGENT_KIT_SIMILARITY_SEARCH_EMBEDDING_DIMENSIONS')
        : null,
      'embedding_timeout_seconds' => env('AI_AGENT_KIT_SIMILARITY_SEARCH_EMBEDDING_TIMEOUT') !== null
        ? (int)env('AI_AGENT_KIT_SIMILARITY_SEARCH_EMBEDDING_TIMEOUT')
        : null,
      'embedding_provider' => env('AI_AGENT_KIT_SIMILARITY_SEARCH_EMBEDDING_PROVIDER'),
      'embedding_model' => env('AI_AGENT_KIT_SIMILARITY_SEARCH_EMBEDDING_MODEL'),
    ],

    'provider_tools' => [
        // 'web.search' => [
        //     'type' => 'web_search',
        //     'enabled' => true,
        //     'max_searches' => 3,
        //     'allowed_domains' => ['example.com'],
        //     'location' => [
        //         'city' => 'Stockholm',
        //         'region' => 'Stockholm County',
        //         'country' => 'SE',
        //     ],
        // ],
        // 'docs.search' => [
        //     'type' => 'file_search',
        //     'enabled' => true,
        //     'stores' => ['store_123'],
        //     'filters' => ['scope' => 'support'],
        // ],
    ],
  ],

    /*
    |--------------------------------------------------------------------------
    | Runtime execution
    |--------------------------------------------------------------------------
    |
    | Optional middleware stack around every AiRuntime::execute call (direct,
    | blueprint, orchestration). List fully-qualified class names in order; each
    | must implement CreativeCrafts\LaravelAiAgentKit\Contracts\Core\RuntimeMiddleware.
    |
    | Optional default broadcast channel for stream lifecycle events (see
    | runtime.streaming). Per-request override: metadata key
    | streaming_broadcast_channel.
    |
    */
  'runtime' => [
    'middleware' => [],

    'streaming' => [
      'broadcast_channel' => env('AI_AGENT_KIT_STREAMING_BROADCAST_CHANNEL'),
    ],
  ],

    /*
    |--------------------------------------------------------------------------
    | Modality runtimes (transcription, embeddings, images, reranking, audio generation)
    |--------------------------------------------------------------------------
    |
    | Each modality resolves a runtime contract from the container. Use
    | `default_driver` => `sdk` for the package Laravel AI bridge, or a
    | fully-qualified class name implementing the modality contract.
    |
    */
  'modalities' => [
    'transcription' => [
      'default_driver' => 'sdk',
    ],
    'embeddings' => [
      'default_driver' => 'sdk',
    ],
    'image_generation' => [
      'default_driver' => 'sdk',
    ],
    'reranking' => [
      'default_driver' => 'sdk',
    ],
    'audio_generation' => [
      'default_driver' => 'sdk',
    ],
  ],

    /*
    |--------------------------------------------------------------------------
    | Conversation Summarization
    |--------------------------------------------------------------------------
    |
    | Summarization is pluggable and explicit. The default implementation is a
    | safe no-op summarizer that preserves any existing summary and never writes
    | a new one. Trigger thresholds are configured centrally so downstream
    | implementations can reuse the same policy surface.
    |
    */
  'summarization' => [
    'enabled' => (bool)env('AI_AGENT_KIT_SUMMARIZATION_ENABLED', false),
    'trigger_message_count' => (int)env('AI_AGENT_KIT_SUMMARIZATION_TRIGGER_MESSAGE_COUNT', 20),
  ],
];
