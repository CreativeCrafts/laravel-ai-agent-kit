# Configuration

Publish the package configuration before editing it:

~~~bash
php artisan vendor:publish --tag="ai-agent-kit-config"
~~~

The package validates `config/ai-agent-kit.php` during boot by default. Invalid provider, budget, memory, vector, tool, or runtime settings should fail early instead of producing unclear runtime behavior.

## Required provider settings

The published config ships a `null` driver profile with **empty** capabilities. That profile is useful for bootstrapping and deterministic package tests, but **blueprints and agents require capability-bearing profiles** (for example `text_generation` and `structured_output` for text evaluation).

For local or production workflows, merge a preset from `examples/provider-profile-presets.php` or define real profiles:

~~~php
'providers' => [
    'openai-structured' => [
        'driver' => 'openai',
        'enabled' => true,
        'capabilities' => ['text_generation', 'structured_output'],
        'options' => [],
    ],
],

'default_provider' => 'openai-structured',

'failover_order' => ['openai-structured'],
~~~

See [Providers](providers.md) for capability names, presets, and failover.

## Validation

~~~php
'validation' => [
    'enabled' => true,
],
~~~

Keep validation enabled in normal application environments. Disable it only for narrow test scenarios where a test intentionally builds partial configuration.

## Ephemeral driver warnings

Optional one-time-per-process warnings when in-memory memory or vector drivers are selected in configured environments (default: production):

~~~php
'ephemeral_driver_warnings' => [
    'enabled' => false,
    'environments' => ['production'],
],
~~~

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

Runtime execution enforces token and tool-call ceilings when usage metadata is available. Cost ceilings are fail-closed: when `max_cost_usd` is configured, requests must provide numeric cost metadata so the package can make deterministic decisions.

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
        'apply_to_failover' => false,
        'failure_threshold' => 3,
        'reset_timeout_seconds' => 60,
        'half_open_success_threshold' => 1,
    ],
],
~~~

Failover and circuit-breaker behavior are package-owned policies. Provider SDK exceptions should be normalized before they become application-facing workflow behavior.

## Runtime

~~~php
'runtime' => [
    'middleware' => [
        // \App\Runtime\LogAiRuntimeRequest::class,
    ],
    'streaming' => [
        'broadcast_channel' => env('AI_AGENT_KIT_STREAMING_BROADCAST_CHANNEL'),
    ],
],
~~~

Runtime middleware wraps package `AiRuntime` execution. Streaming is covered in [Streaming and modalities](streaming-and-modalities.md).

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

## Related guides

- [Getting started](getting-started.md)
- [Providers](providers.md)
- [Memory](memory.md)
- [Tools](tools.md)
- [Pipelines and queues](pipelines-and-queues.md)
- [Vectors and retrieval](vectors-and-retrieval.md)
- [Production](production.md)
