# Configuration reference

The package validates `config/ai-agent-kit.php` during boot by default. Publish the file with:

~~~bash
php artisan vendor:publish --tag="ai-agent-kit-config"
~~~

## Providers and failover

At least one enabled provider must exist, `default_provider` must reference an enabled configured provider, and `failover_order` must include the default provider.

## Memory

The default memory driver is `in_memory`. That default is explicit, non-persistent, and safe for tests, local development, and ephemeral runs. Switch `memory.default_driver` to `database` when you
want encrypted persistent storage and retention-based purging, or to `redis` when you need shared ephemeral memory across workers. Optional **`memory.laravel_ai_legacy`** reads Laravel AI’s default `agent_*` tables when your package store has no row (database driver only; see config comments in `memory.laravel_ai_legacy`). Optional **`memory.attachments_replay`** gates replay of persisted attachment references on continued conversations (opt-in per request via `metadata['attachment_replay']`; see `memory.attachments_replay` in `config/ai-agent-kit.php`).

## Budgets and pipelines

Retry and circuit breaker resilience settings are configured explicitly under `resilience`. Retry policy evaluation is bounded by `budgets.max_retries_per_step` and is enforced by the synchronous
pipeline runner at execution time.

Pipeline execution enforces both `budgets.max_steps` and `budgets.max_total_timeout_seconds` with typed budget exceptions.

## Runtime

Runtime execution enforces `budgets.max_tokens` and `budgets.max_tool_calls` using SDK usage/tool-call telemetry.

Optional **runtime middleware** wraps every `AiRuntime::execute` call (including blueprints and orchestration): register ordered class names under `runtime.middleware` in `config/ai-agent-kit.php`. Each class must implement `CreativeCrafts\LaravelAiAgentKit\Contracts\Core\RuntimeMiddleware`. Implement `TerminatingRuntimeMiddleware` when you need a reverse-order hook after a successful response.

**Streaming text** uses the Laravel AI SDK stream path for non-schema requests. Inject `CreativeCrafts\LaravelAiAgentKit\Contracts\Core\StreamingAiRuntime` and iterate `executeStream($request)` to receive `StreamChunk` values (ordered `text_delta` segments) followed by a single terminal `StreamComplete` or `StreamFailure`. Structured-output requests (`ExecutionRequest::$schema` set) are not supported for streaming; use `execute()` instead. Optional Echo broadcast: set `runtime.streaming.broadcast_channel` (or per-request metadata `streaming_broadcast_channel`) to a **public** channel name; the package dispatches `RuntimeStreamChunkEmitted`, `RuntimeStreamCompleted`, and `RuntimeStreamFailed` with **redacted** payloads (lengths, keys, identifiers — no prompt text).

## Modalities

**Modality runtimes** (transcription, embeddings, image generation, reranking, **audio generation**) use contracts under `CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\`. The default driver is `sdk`, which bridges to Laravel AI (`Transcription`, `Embeddings`, `Image`, `Reranking`, `Audio`). Override per modality with `modalities.<name>.default_driver` in `config/ai-agent-kit.php` (`sdk` or a class implementing the contract). The `AudioToTextToEvaluation` blueprint calls the transcription runtime when `audio_reference` is raw base64 or a `data:*;base64,...` URI; opaque references (for example `s3://...`) still use the registered prompt plus `AiRuntime`.

## Laravel AI Files and Stores

**Laravel AI provider Files and Stores** — For provider-hosted uploads and vector stores (RAG with `FileSearch`), inject `CreativeCrafts\LaravelAiAgentKit\Core\LaravelAi\LaravelAiFilesService` and `LaravelAiStoresService`. They wrap `Laravel\Ai\Files` and `Laravel\Ai\Stores` and return package DTOs. Optional `laravel_ai_files.default_provider` and `laravel_ai_stores.default_provider` apply when you omit the provider argument. This is separate from **`VectorStoreInterface`** (application embedding storage with `in_memory` / `database` drivers).

## Similarity search tool

**Similarity search custom tool** — Optional package tool `similarity_search` (class `CreativeCrafts\LaravelAiAgentKit\Tools\SimilaritySearchTool`) searches documents in `VectorStoreInterface` using query embeddings from `EmbeddingsRuntime`. Built-in vector stores enforce **one embedding width per namespace**; set `tools.similarity_search.embedding_dimensions` to match your embedding model when you use a custom `VectorStoreInterface` that does not implement `VectorStoreReferenceEmbedding`. Enable with `tools.similarity_search.enabled` and `tools.similarity_search.register` in `config/ai-agent-kit.php`, then list `similarity_search` in `ExecutionRequest::$toolNames` and implement `ToolAuthorizer` so custom tools are allowed (the default authorizer denies all). This targets the kit vector store; for Eloquent `whereVectorSimilarTo` flows, Laravel AI’s `Laravel\Ai\Tools\SimilaritySearch` remains the right choice.

## Cost and circuit breaker

`budgets.max_cost_usd` is enforced in fail-closed mode: when configured, each runtime request must provide numeric `metadata.cost_usd` (or `metadata.estimated_cost_usd`) so cost ceilings can be
validated deterministically.

Circuit breaker state can be applied to failover selection by enabling `resilience.circuit_breaker.apply_to_failover` (opt-in to preserve previous defaults).

## Prompts

Prompt repositories support two drivers via `prompts.default_driver`:

- `in_memory` (default)
- `file` (loads prompt metadata and templates from `resources/prompts` or `prompts.file.root_path`)

## Telemetry

Pipeline lifecycle and failover telemetry are emitted through Laravel events with redacted defaults. Event payloads expose safe metadata such as run identifiers, provider names, step classes, counts,
and key lists rather than raw prompt, input, metadata, or provider option values by default.

### Vector embeddings (built-in stores)

`InMemoryVectorStore`, `DatabaseVectorStore`, and the test fake `FakeVectorStore` enforce **one vector length per namespace**: the first `upsert` into an empty namespace sets the width, and every later document in that namespace must match. Mismatched `upsert` calls throw `VectorOperationException` (see `forEmbeddingDimensionMismatch`). `search` only scores documents whose stored embedding length **equals** the query vector length (other rows are skipped).

Use separate namespaces (or a custom `VectorStoreInterface`) if you intentionally mix embedding models.

### Laravel AI Files and Stores observability

`LaravelAiFilesService` and `LaravelAiStoresService` dispatch redacted events `LaravelAiFilesGatewayOperationFinished` and `LaravelAiStoresGatewayOperationFinished` (operation name, provider, ids, success, bounded error text — **no** file bodies or API keys). Toggle with `observability.laravel_ai_files_stores.enabled` (default **true**). In tests that assert global event counts, you may set it to `false`.

### Queued pipelines and `RunContext`

`RunQueuedPipelineJob` serializes the entire `RunContext`. Prefer small, serializable `input` / `state` / `metadata`; avoid putting a full `Conversation` graph on the job when a `conversationId` is enough.

| Field | Notes |
|-------|--------|
| `runId` | Correlation id for the run. |
| `input` | Associative map; avoid non-serializable objects. |
| `state` | Pipeline step state; same caution as `input`. |
| `metadata` | Small key/value bag for cross-cutting data. |
| `stepCount`, `toolCallCount` | Integers. |
| `selectedProvider` | Nullable provider key string. |
| `conversationId` | Nullable `ConversationId` value object (serializable). |
| `conversation` | Optional full `Conversation` graph — **large** if populated; prefer `conversationId` and load in the worker when possible. |
| `storeConversation`, `continueConversation` | Booleans. |

Optional dev guard: when `pipeline.queued.debug_payload_guard` is true and `config('app.debug')` is true, dispatch fails if the serialized job exceeds `pipeline.queued.max_serialized_job_bytes` (default 512 KiB).

## Example configuration

~~~php
return [
    'validation' => [
        'enabled' => true,
    ],

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
        'max_steps' => 20,
        'max_tool_calls' => 50,
        'max_retries_per_step' => 2,
        'max_total_timeout_seconds' => 120,
        'max_tokens' => null,
        'max_cost_usd' => null,
    ],

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

    'runtime' => [
        'middleware' => [
            // \App\Runtime\LogAiRuntimeRequest::class,
        ],
        'streaming' => [
            'broadcast_channel' => env('AI_AGENT_KIT_STREAMING_BROADCAST_CHANNEL'),
        ],
    ],

    'modalities' => [
        'transcription' => ['default_driver' => 'sdk'],
        'embeddings' => ['default_driver' => 'sdk'],
        'image_generation' => ['default_driver' => 'sdk'],
        'reranking' => ['default_driver' => 'sdk'],
        'audio_generation' => ['default_driver' => 'sdk'],
    ],

    'prompts' => [
        'default_driver' => 'in_memory',

        'file' => [
            'root_path' => null,
        ],
    ],

    'memory' => [
        'default_driver' => 'in_memory',

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
            'connection' => null,
            'prefix' => 'ai_agent_memory:',
            'driver_name' => 'redis',
            'retention_days' => 7,
        ],

        // Optional: read Laravel AI default `agent_*` tables when package store misses.
        // 'laravel_ai_legacy' => [
        //     'enabled' => true,
        //     'connection' => null,
        //     'conversations_table' => 'agent_conversations',
        //     'messages_table' => 'agent_conversation_messages',
        // ],

        // Optional: attachment replay policy when continuing conversations.
        // 'attachments_replay' => [
        //     'enabled' => false,
        //     'max_per_turn' => null,
        //     'max_age_seconds' => null,
        //     'allow_provider_references' => true,
        //     'deny_types' => ['base64-image', 'base64-document', 'base64-audio', 'local-image', 'local-document', 'local-audio'],
        //     'deny_url_substrings' => [],
        // ],
    ],

    'vector' => [
        'default_driver' => 'in_memory',
        'in_memory' => [
            'enabled' => true,
        ],
        // 'database' => [
        //     'connection' => null,
        //     'table' => 'ai_agent_vector_documents',
        // ],
    ],
];
~~~

## `AgentKit` facade vs injection

The `AgentKit` facade resolves `AgentKitManager`, which exposes blueprint/orchestration helpers plus thin delegates for **`executeStream`**, modality methods (`embed`, `transcribe`, `generateImage`, `rerank`, `generateAudio`), and **`laravelAiFiles()`** / **`laravelAiStores()`**. Each delegate uses the same container bindings as `app(Contract::class)`. Prefer **constructor injection** of the specific contract (`StreamingAiRuntime`, `EmbeddingsRuntime`, etc.) in application services and jobs so dependencies stay explicit and test doubles swap cleanly; use the facade for routes, one-off commands, or quick exploration.

## Tool input schema support (in-memory registry)

`InMemoryToolRegistry` intentionally validates a constrained, deterministic schema subset for runtime tool input validation:

- Root schema must be `type: object`.
- `properties` must be a top-level object map.
- Each property must declare one supported `type`: `string`, `integer`, `number`, `boolean`, `array`, or `object`.
- `required` must be a list of declared property names.
- `additionalProperties` may be set to `true` or `false`.

Nested JSON Schema features (for example nested `properties`, `items`, `oneOf`, or format/pattern constraints) are currently out of scope for the in-memory validator.

## Related guides

- [Orchestration and blueprints](orchestration-and-blueprints.md)
- [Pipelines, queues, memory, and vectors](pipelines-queues-and-memory.md)
- [Testing with fakes](testing-with-fakes.md)
