# Configuration

Publish the package configuration before editing it:

~~~bash
php artisan vendor:publish --tag="ai-agent-kit-config"
~~~

The package validates `config/ai-agent-kit.php` during boot by default. Invalid provider, budget, memory, vector, tool, or runtime settings should fail early instead of producing unclear runtime behavior.

## Required provider settings

The published config ships a `null` driver profile with **empty** capabilities. That profile is useful for bootstrapping and deterministic package tests, but **blueprints and agents require capability-bearing profiles** (for example `text_generation` and `structured_output` for text evaluation).

For local or production workflows, merge a preset from `examples/provider-profile-presets.php` or define real profiles. Keep the Agent Kit profile name, Laravel AI provider instance, and driver distinct when they are not the same string:

~~~php
'providers' => [
    'primary-image-scorer' => [
        'driver' => 'openai',
        'sdk_provider' => 'openai-production',
        'enabled' => true,
        'capabilities' => [
            'text_generation',
            'structured_output',
            'vision',
        ],
        'options' => [
            'model' => 'gpt-example',
            'provider_options' => [
                'reasoning' => [
                    'effort' => 'medium',
                ],
            ],
        ],
    ],
],

'default_provider' => 'primary-image-scorer',

'failover_order' => ['primary-image-scorer'],
~~~

`sdk_provider` names the Laravel AI instance in `config/ai.php`. When omitted, Agent Kit uses `driver`. See [Providers](providers.md) for the identity model, capability names, presets, and failover.

`resilience.failover.model_policy` controls whether an explicit request model is reused after provider failover. Its default, `initial_only`, isolates fallback profiles. `preserve_when_same_sdk_provider` reuses the model only when both profiles resolve to the same Laravel AI provider, while `preserve_always_legacy` restores the previous broad propagation behavior.

## Validation

~~~php
'validation' => [
    'enabled' => true,
],
~~~

Keep validation enabled in normal application environments. Disable it only for narrow test scenarios where a test intentionally builds partial configuration.

## Ephemeral driver warnings

Optional one-time-per-process warnings when in-memory memory or vector drivers are selected in configured environments (default: production). Enabled by default:

~~~php
'ephemeral_driver_warnings' => [
    'enabled' => true,
    'environments' => ['production'],
],
~~~

## Media input security

URL-bearing DTOs (`EvaluationImageInput::fromUrl()`, `TranscriptionAudioSource::fromUrl()`) support explicit transport and allowlist policies. The shipped values preserve 1.x behavior; production applications can opt into the recommended secure profile with HTTPS and exact host matching:

~~~php
'media_input' => [
    'require_https' => true,
    'host_match' => 'exact_only',
    'include_diagnostic_names' => false,
    'url_allowed_hosts' => ['cdn.example.com', 'example.test'],
],
~~~

`exact_and_subdomains` preserves the compatibility behavior where `cdn.example.com` matches an `example.com` entry. Diagnostic filenames and path basenames are omitted by default because they may contain personal or tenant data.

See [Streaming and modalities](streaming-and-modalities.md#media-input-trust-boundaries).

## Budgets

Budgets protect workflows from unbounded execution:

~~~php
'budgets' => [
    'max_steps' => 20,
    'max_orchestration_depth' => 25,
    'max_tool_calls' => 50,
    'max_retries_per_step' => 2,
    'max_total_timeout_seconds' => 120,
    'max_tokens' => null,
    'max_cost_usd' => null,
],
~~~

Runtime execution separates preflight cost checks from postflight token and tool-call checks. `CostEstimator` is package-owned and defaults to the compatibility `RequestMetadataCostEstimator`, which reads `cost_usd` or `estimated_cost_usd` as a declared estimate—not actual billed cost. With `cost_estimation_mode=strict`, a missing or over-limit estimate fails before provider dispatch. `advisory` mode dispatches an unknown estimate and returns `cost_unknown=true` telemetry.

## Orchestration

Delegation policy controls which agent handoffs the orchestrator may approve:

~~~php
'orchestration' => [
    'delegation_policy' => [
        'mode' => 'static_only',
        'allow_dynamic_delegation' => false,
        'allowlist' => [],
        'rewrites' => [],
    ],
],
~~~

Supported modes:

- `static_only` — only targets declared on the delegating agent's `delegationTargets`
- `dynamic_with_allowlist` — static targets plus per-agent entries in `allowlist` (requires `allow_dynamic_delegation`)
- `dynamic_full_registry` — any registered agent may be targeted (requires `allow_dynamic_delegation`)

Set `AI_AGENT_KIT_ORCHESTRATION_ALLOW_DYNAMIC_DELEGATION=true` (or `allow_dynamic_delegation => true`) before enabling a non-static mode.

See [Agents and orchestration](agents-and-orchestration.md).

## Resilience

~~~php
'resilience' => [
    'failure_classification' => [
        'unknown_failure_mode' => 'strict',
    ],
    'retry' => [
        'enabled' => true,
        'max_attempts' => 3,
        'backoff' => [
            'strategy' => 'exponential',
            'base_delay_ms' => 250,
            'max_delay_ms' => 2000,
            'multiplier' => 2.0,
        ],
    ],
    'circuit_breaker' => [
        'enabled' => true,
        'driver' => 'cache',
        'cache_store' => 'redis',
        'key_prefix' => 'ai-agent-kit:circuit-breaker:',
        'lock_seconds' => 5,
        'apply_to_failover' => false,
        'failure_threshold' => 3,
        'reset_timeout_seconds' => 60,
        'half_open_success_threshold' => 1,
    ],
],
~~~

Failover and circuit-breaker behavior are package-owned policies. Use the `cache` circuit-breaker driver with a shared lock-capable cache store for state shared across HTTP and queue workers. The compatibility default, `in_memory`, is process-local and does not share state between workers. Cache state transitions are performed under atomic cache locks; manager resolution fails if the selected store cannot provide them.

In `strict` failure-classification mode, an unclassified throwable fails closed without consuming fallback profiles or damaging provider health. `legacy_failover` temporarily restores the previous broad failover behavior for unclassified throwables while an application migrates to typed failures.

## Runtime

~~~php
'runtime' => [
    'middleware' => [
        // \App\Runtime\LogAiRuntimeRequest::class,
    ],
    'default_instructions' => [],
    'streaming' => [
        'broadcast_channel' => env('AI_AGENT_KIT_STREAMING_BROADCAST_CHANNEL'),
    ],
],
~~~

Runtime middleware wraps package `AiRuntime` execution. `default_instructions` is empty by default so Agent Kit does not invent a system persona. Set a string or list of strings only when you want an explicit package-level default for requests that supply no instructions. Streaming is covered in [Streaming and modalities](streaming-and-modalities.md).

## Modalities

Each modality resolves a runtime contract from the container. Use `default_driver` => `sdk` for the Laravel AI bridge, or a class implementing the modality contract:

~~~php
'modalities' => [
    'transcription' => ['default_driver' => 'sdk'],
    'embeddings' => ['default_driver' => 'sdk'],
    'image_generation' => ['default_driver' => 'sdk'],
    'reranking' => ['default_driver' => 'sdk'],
    'audio_generation' => ['default_driver' => 'sdk'],
],
~~~

See [Streaming and modalities](streaming-and-modalities.md).

## Memory

~~~php
'memory' => [
    'default_driver' => 'in_memory',
],
~~~

Use `in_memory` for local and test usage, `database` for encrypted durable storage, and `redis` for shared ephemeral memory across workers.

Additional memory keys:

- `memory.laravel_ai_legacy` — read fallback to Laravel AI conversation tables when using the database driver
- `memory.attachments_replay` — opt-in replay of persisted attachments on conversation continuation; `allow_provider_references` defaults to `false`

See [Memory](memory.md).

## Vectors

~~~php
'vector' => [
    'default_driver' => 'in_memory',
],
~~~

Use `database` or a custom `VectorStoreInterface` implementation for shared retrieval. Built-in stores enforce one embedding width per namespace. See [Vectors and retrieval](vectors-and-retrieval.md).

## Laravel AI Files and Stores

Optional default provider keys for package facades over Laravel AI Files and Stores APIs:

~~~php
'laravel_ai_files' => [
    'default_provider' => env('AI_AGENT_KIT_LARAVEL_AI_FILES_PROVIDER'),
],

'laravel_ai_stores' => [
    'default_provider' => env('AI_AGENT_KIT_LARAVEL_AI_STORES_PROVIDER'),
],
~~~

See [Vectors and retrieval](vectors-and-retrieval.md).

## Tools

Tool execution remains default-deny until your app registers tools and authorizes execution.

Packaged tool configuration:

~~~php
'tools' => [
    'authorizer' => \CreativeCrafts\LaravelAiAgentKit\Tools\DenyAllToolAuthorizer::class,
    'similarity_search' => [
        'enabled' => false,
        'register' => false,
    ],
    'provider_tools' => [
        // 'web.search' => ['type' => 'web_search', 'enabled' => true],
        // 'docs.search' => ['type' => 'file_search', 'enabled' => true, 'stores' => ['store_123']],
    ],
],
~~~

Provider-native tools (`web_search`, `file_search`) are enabled only through explicit `provider_tools` aliases. See [Tools](tools.md).

## Queued pipelines

~~~php
'pipeline' => [
    'queued' => [
        'payload_guard' => true,
        'debug_payload_guard' => false,
        'max_serialized_job_bytes' => 524288,
    ],
],
~~~

See [Pipelines and queues](pipelines-and-queues.md).

## Summarization

Conversation summarization is pluggable. The default is a no-op summarizer:

~~~php
'summarization' => [
    'enabled' => false,
    'trigger_message_count' => 20,
],
~~~

## Observability

Telemetry events are redacted by default. Files/Stores gateway observability can be toggled through:

~~~php
'observability' => [
    'laravel_ai_files_stores' => [
        'enabled' => true,
    ],
],
~~~

See [Errors and telemetry](errors-and-telemetry.md).

## Scaffolding and maintenance commands

After install, the package registers:

~~~bash
php artisan ai:make:agent Support/ReplyAgent
php artisan ai:make:pipeline Support/ReplyPipeline
php artisan ai:make:prompt Support.Reply --prompt-version=1.0.0
php artisan ai:make:tool Support/LookupCustomer
php artisan ai:purge:conversations
~~~

`ai:purge:conversations` removes expired conversation records according to the configured memory driver retention policy.

## Environment variable reference

Every setting above can be driven from `.env`. The tables below map each variable to its config key and shipped default. Prefer editing the published `config/ai-agent-kit.php` for structured values (arrays, class names); use environment variables for per-environment scalars.

### Core, validation, and providers

| Variable | Config key | Default |
|---|---|---|
| `AI_AGENT_KIT_VALIDATE_CONFIG` | `validation.enabled` | `true` |
| `AI_AGENT_KIT_EPHEMERAL_DRIVER_WARNINGS` | `ephemeral_driver_warnings.enabled` | `true` |
| `AI_AGENT_KIT_EPHEMERAL_WARN_ENVIRONMENTS` | `ephemeral_driver_warnings.environments` | `production` |
| `AI_AGENT_KIT_DEFAULT_PROVIDER` | `default_provider` | `null` |
| `AI_AGENT_KIT_FAILOVER_ORDER` | `failover_order` | `null` |
| `AI_AGENT_KIT_MEDIA_URL_ALLOWED_HOSTS` | `media_input.url_allowed_hosts` | *(empty)* |
| `AI_AGENT_KIT_MEDIA_REQUIRE_HTTPS` | `media_input.require_https` | `false` |
| `AI_AGENT_KIT_MEDIA_HOST_MATCH` | `media_input.host_match` | `exact_and_subdomains` |
| `AI_AGENT_KIT_MEDIA_INCLUDE_DIAGNOSTIC_NAMES` | `media_input.include_diagnostic_names` | `false` |

### Budgets and orchestration

| Variable | Config key | Default |
|---|---|---|
| `AI_AGENT_KIT_MAX_STEPS` | `budgets.max_steps` | `20` |
| `AI_AGENT_KIT_MAX_ORCHESTRATION_DEPTH` | `budgets.max_orchestration_depth` | `25` |
| `AI_AGENT_KIT_MAX_TOOL_CALLS` | `budgets.max_tool_calls` | `50` |
| `AI_AGENT_KIT_MAX_RETRIES_PER_STEP` | `budgets.max_retries_per_step` | `2` |
| `AI_AGENT_KIT_MAX_TOTAL_TIMEOUT_SECONDS` | `budgets.max_total_timeout_seconds` | `120` |
| `AI_AGENT_KIT_MAX_TOKENS` | `budgets.max_tokens` | `null` |
| `AI_AGENT_KIT_MAX_COST_USD` | `budgets.max_cost_usd` | `null` |
| `AI_AGENT_KIT_COST_ESTIMATION_MODE` | `budgets.cost_estimation_mode` | `strict` |
| `AI_AGENT_KIT_ORCHESTRATION_DELEGATION_POLICY_MODE` | `orchestration.delegation_policy.mode` | `static_only` |
| `AI_AGENT_KIT_ORCHESTRATION_ALLOW_DYNAMIC_DELEGATION` | `orchestration.delegation_policy.allow_dynamic_delegation` | `false` |

### Resilience (retry and circuit breaker)

| Variable | Config key | Default |
|---|---|---|
| `AI_AGENT_KIT_UNKNOWN_FAILURE_MODE` | `resilience.failure_classification.unknown_failure_mode` | `strict` |
| `AI_AGENT_KIT_RETRY_ENABLED` | `resilience.retry.enabled` | `true` |
| `AI_AGENT_KIT_RETRY_MAX_ATTEMPTS` | `resilience.retry.max_attempts` | `3` |
| `AI_AGENT_KIT_RETRY_BACKOFF_STRATEGY` | `resilience.retry.backoff.strategy` | `exponential` |
| `AI_AGENT_KIT_RETRY_BACKOFF_BASE_DELAY_MS` | `resilience.retry.backoff.base_delay_ms` | `250` |
| `AI_AGENT_KIT_RETRY_BACKOFF_MAX_DELAY_MS` | `resilience.retry.backoff.max_delay_ms` | `2000` |
| `AI_AGENT_KIT_RETRY_BACKOFF_MULTIPLIER` | `resilience.retry.backoff.multiplier` | `2.0` |
| `AI_AGENT_KIT_CIRCUIT_BREAKER_ENABLED` | `resilience.circuit_breaker.enabled` | `true` |
| `AI_AGENT_KIT_CIRCUIT_BREAKER_DRIVER` | `resilience.circuit_breaker.driver` | `in_memory` |
| `AI_AGENT_KIT_CIRCUIT_BREAKER_CACHE_STORE` | `resilience.circuit_breaker.cache_store` | `null` |
| `AI_AGENT_KIT_CIRCUIT_BREAKER_KEY_PREFIX` | `resilience.circuit_breaker.key_prefix` | `ai-agent-kit:circuit-breaker:` |
| `AI_AGENT_KIT_CIRCUIT_BREAKER_LOCK_SECONDS` | `resilience.circuit_breaker.lock_seconds` | `5` |
| `AI_AGENT_KIT_CIRCUIT_BREAKER_APPLY_TO_FAILOVER` | `resilience.circuit_breaker.apply_to_failover` | `false` |
| `AI_AGENT_KIT_CIRCUIT_BREAKER_FAILURE_THRESHOLD` | `resilience.circuit_breaker.failure_threshold` | `3` |
| `AI_AGENT_KIT_CIRCUIT_BREAKER_RESET_TIMEOUT_SECONDS` | `resilience.circuit_breaker.reset_timeout_seconds` | `60` |
| `AI_AGENT_KIT_CIRCUIT_BREAKER_HALF_OPEN_SUCCESS_THRESHOLD` | `resilience.circuit_breaker.half_open_success_threshold` | `1` |

### Runtime, prompts, and summarization

| Variable | Config key | Default |
|---|---|---|
| `AI_AGENT_KIT_STREAMING_BROADCAST_CHANNEL` | `runtime.streaming.broadcast_channel` | `null` |
| `AI_AGENT_KIT_PROMPTS_DRIVER` | `prompts.default_driver` | `in_memory` |
| `AI_AGENT_KIT_PROMPTS_FILE_ROOT_PATH` | `prompts.file.root_path` | `null` |
| `AI_AGENT_KIT_SUMMARIZATION_ENABLED` | `summarization.enabled` | `false` |
| `AI_AGENT_KIT_SUMMARIZATION_TRIGGER_MESSAGE_COUNT` | `summarization.trigger_message_count` | `20` |

### Memory

| Variable | Config key | Default |
|---|---|---|
| `AI_AGENT_KIT_MEMORY_DRIVER` | `memory.default_driver` | `in_memory` |
| `AI_AGENT_KIT_MEMORY_IN_MEMORY_RETENTION_DAYS` | `memory.in_memory.retention_days` | `null` |
| `AI_AGENT_KIT_MEMORY_DB_CONNECTION` | `memory.database.connection` | `null` |
| `AI_AGENT_KIT_MEMORY_CONVERSATIONS_TABLE` | `memory.database.conversations_table` | `ai_agent_conversations` |
| `AI_AGENT_KIT_MEMORY_MESSAGES_TABLE` | `memory.database.messages_table` | `ai_agent_conversation_messages` |
| `AI_AGENT_KIT_MEMORY_DATABASE_DRIVER_NAME` | `memory.database.driver_name` | `database` |
| `AI_AGENT_KIT_MEMORY_RETENTION_DAYS` | `memory.database.retention_days` | `30` |
| `AI_AGENT_KIT_MEMORY_ENCRYPT_PAYLOADS` | `memory.database.encrypt_payloads` | `true` |
| `AI_AGENT_KIT_MEMORY_REDIS_CONNECTION` | `memory.redis.connection` | `null` |
| `AI_AGENT_KIT_MEMORY_REDIS_PREFIX` | `memory.redis.prefix` | `ai_agent_memory:` |
| `AI_AGENT_KIT_MEMORY_REDIS_DRIVER_NAME` | `memory.redis.driver_name` | `redis` |
| `AI_AGENT_KIT_MEMORY_REDIS_RETENTION_DAYS` | `memory.redis.retention_days` | `null` |
| `AI_AGENT_KIT_MEMORY_REDIS_ENCRYPT_PAYLOADS` | `memory.redis.encrypt_payloads` | `true` |
| `AI_AGENT_KIT_MEMORY_LARAVEL_AI_LEGACY_FALLBACK` | `memory.laravel_ai_legacy.enabled` | `false` |
| `AI_AGENT_KIT_MEMORY_LARAVEL_AI_LEGACY_CONNECTION` | `memory.laravel_ai_legacy.connection` | `null` |
| `AI_AGENT_KIT_MEMORY_LARAVEL_AI_LEGACY_CONVERSATIONS_TABLE` | `memory.laravel_ai_legacy.conversations_table` | `agent_conversations` |
| `AI_AGENT_KIT_MEMORY_LARAVEL_AI_LEGACY_MESSAGES_TABLE` | `memory.laravel_ai_legacy.messages_table` | `agent_conversation_messages` |
| `AI_AGENT_KIT_MEMORY_ATTACHMENTS_REPLAY_ENABLED` | `memory.attachments_replay.enabled` | `false` |
| `AI_AGENT_KIT_MEMORY_ATTACHMENTS_REPLAY_MAX_PER_TURN` | `memory.attachments_replay.max_per_turn` | `null` |
| `AI_AGENT_KIT_MEMORY_ATTACHMENTS_REPLAY_MAX_AGE_SECONDS` | `memory.attachments_replay.max_age_seconds` | `null` |
| `AI_AGENT_KIT_MEMORY_ATTACHMENTS_REPLAY_ALLOW_PROVIDER_REFERENCES` | `memory.attachments_replay.allow_provider_references` | `false` |

### Vectors and similarity search

| Variable | Config key | Default |
|---|---|---|
| `AI_AGENT_KIT_VECTOR_DRIVER` | `vector.default_driver` | `in_memory` |
| `AI_AGENT_KIT_VECTOR_IN_MEMORY_ENABLED` | `vector.in_memory.enabled` | `true` |
| `AI_AGENT_KIT_VECTOR_DATABASE_CONNECTION` | `vector.database.connection` | `null` |
| `AI_AGENT_KIT_VECTOR_DATABASE_TABLE` | `vector.database.table` | `ai_agent_vector_documents` |
| `AI_AGENT_KIT_VECTOR_DATABASE_MAX_SCAN_ROWS` | `vector.database.max_scan_rows` | `null` |
| `AI_AGENT_KIT_SIMILARITY_SEARCH_ENABLED` | `tools.similarity_search.enabled` | `false` |
| `AI_AGENT_KIT_SIMILARITY_SEARCH_REGISTER` | `tools.similarity_search.register` | `false` |
| `AI_AGENT_KIT_SIMILARITY_SEARCH_NAME` | `tools.similarity_search.name` | `similarity_search` |
| `AI_AGENT_KIT_SIMILARITY_SEARCH_NAMESPACE` | `tools.similarity_search.default_namespace` | `default` |
| `AI_AGENT_KIT_SIMILARITY_SEARCH_LIMIT` | `tools.similarity_search.default_limit` | `10` |
| `AI_AGENT_KIT_SIMILARITY_SEARCH_EMBEDDING_DIMENSIONS` | `tools.similarity_search.embedding_dimensions` | `null` |
| `AI_AGENT_KIT_SIMILARITY_SEARCH_EMBEDDING_TIMEOUT` | `tools.similarity_search.embedding_timeout_seconds` | `null` |
| `AI_AGENT_KIT_SIMILARITY_SEARCH_EMBEDDING_PROVIDER` | `tools.similarity_search.embedding_provider` | `null` |
| `AI_AGENT_KIT_SIMILARITY_SEARCH_EMBEDDING_MODEL` | `tools.similarity_search.embedding_model` | `null` |

### Queued pipelines, Files/Stores, and observability

| Variable | Config key | Default |
|---|---|---|
| `AI_AGENT_KIT_QUEUED_PIPELINE_PAYLOAD_GUARD` | `pipeline.queued.payload_guard` | `true` |
| `AI_AGENT_KIT_QUEUED_PIPELINE_DEBUG_PAYLOAD_GUARD` | `pipeline.queued.debug_payload_guard` | `false` |
| `AI_AGENT_KIT_QUEUED_PIPELINE_MAX_SERIALIZED_BYTES` | `pipeline.queued.max_serialized_job_bytes` | `524288` |
| `AI_AGENT_KIT_LARAVEL_AI_FILES_PROVIDER` | `laravel_ai_files.default_provider` | `null` |
| `AI_AGENT_KIT_LARAVEL_AI_STORES_PROVIDER` | `laravel_ai_stores.default_provider` | `null` |
| `AI_AGENT_KIT_LARAVEL_AI_FILES_STORES_OBSERVABILITY` | `observability.laravel_ai_files_stores.enabled` | `true` |

## Related guides

- [Getting started](getting-started.md)
- [Providers](providers.md)
- [Memory](memory.md)
- [Tools](tools.md)
- [Pipelines and queues](pipelines-and-queues.md)
- [Vectors and retrieval](vectors-and-retrieval.md)
- [Production](production.md)
