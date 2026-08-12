# SDK capability matrix

This maintainer reference maps Laravel AI SDK surfaces to Agent Kit entry points. Keep it current when upgrading `laravel/ai` or adding package wrappers around SDK capabilities.

## Audit metadata

| Field | Value |
|-------|-------|
| Audit target | `laravel/ai ^0.9` from `composer.json` |
| Lockfile status | This repository does not commit `composer.lock`. Record the resolved `laravel/ai` patch with `composer show laravel/ai` when updating maintainer inventories. |
| Resolved SDK version | `v0.9.1` (`composer show laravel/ai`, 2026-08-12) |
| Last parity sweep | 2026-08-12 — Agent Kit provider-profile / SDK-provider identity split, typed generation options, and strict structured-output bridge |
| Classification values | `package-owned`, `direct-SDK`, `deferred`, `out-of-scope` |

## Principle

Laravel AI SDK is the internal execution substrate. Agent Kit owns workflow composition, provider policy, prompt governance, tool governance, memory policy, redacted telemetry, and package-owned public contracts.

Agent Kit does not mirror every SDK class. If an SDK surface does not need package policy, memory, workflow composition, telemetry normalization, or fake support, direct SDK usage is acceptable and should be documented.

## Runtime and agents

| Laravel AI SDK surface | Agent Kit classification | Agent Kit surface | Rationale / follow-up |
|------------------------|--------------------------|-------------------|-----------------------|
| Anonymous/text agents | package-owned | `AiRuntime`, `ExecutionRequest`, `ExecutionResult`, `AgentKit::run()` | Runtime calls need provider policy, memory projection, tools, budgets, telemetry, and package DTOs. |
| Structured output | package-owned | `ExecutionRequest::$schema`, `ExecutionResult::$structuredOutput` | Schema-backed execution is part of package blueprint/runtime behavior. |
| Multimodal image/text structured execution | package-owned | `ExecutionRequest::$attachments`; `EvaluationImageInput`; `AudioImageStructuredEvaluation` | Package-owned DTOs hide SDK image attachment objects while preserving schema/provider policy. `ExecutionRequest` also accepts raw `Laravel\Ai\Files\File` attachments (including provider-hosted IDs) for advanced integrators. |
| Blueprint image `fromId()` / provider-hosted image references | deferred | none (`EvaluationImageInput` has no `fromId()`) | Use raw `ExecutionRequest` attachments or Files gateway wrappers until a package DTO is promoted. |
| Streaming text | package-owned | `StreamingAiRuntime`, `AgentKit::executeStream()` | Streaming needs package failure normalization, lifecycle telemetry, and conservative failover semantics. |
| Streaming reasoning, citations, and in-stream tool events | deferred | none (`SdkAiRuntime` forwards `TextDelta` only) | SDK v0.8.1 also emits `Reasoning*`, `Citation`, `ProviderToolEvent`, `ToolCall`, and `ToolResult` stream events. Add package `StreamChunk` types only if applications need first-class access without SDK listeners. |
| SDK failover lifecycle events | direct-SDK | none (`SdkAiRuntime` owns failover) | SDK emits `AgentFailedOver` and `ProviderFailedOver`; package runtime retries via configured failover order without normalizing these SDK events. |
| SDK broadcast agent jobs | direct-SDK | none | Use SDK jobs directly when the application wants SDK-specific broadcast-channel behavior instead of package runtime streaming. |
| Generation options | package-owned | `GenerationOptions` on `ExecutionRequest` | Typed fields map to Laravel AI agent methods (`maxTokens()`, `maxSteps()`, `temperature()`). Raw `providerOptions` stay on `HasProviderOptions`. Profile `options.provider_options` merge per attempt. |
| Provider selection | package-owned | provider profiles, `ProviderSelector`, `FailoverProviderSelector`, `ProviderTargetResolver` | Package workflows reason over provider profiles. The Laravel AI bridge receives `sdk_provider` (defaulting to `driver`). |
| SDK middleware / agent prompt internals | out-of-scope | package memory bridge and runtime middleware | Agent Kit owns runtime middleware and memory behavior rather than exposing SDK internals. |
| Conversation context | package-owned | `ConversationStore`, `RuntimeConversationMemoryBridge` | Memory retention, encryption, and replay policy are package concerns. |
| Provider failover execution | package-owned | `SdkAiRuntime` failover loop | Runtime retries provider-edge failures through configured failover order. |

## Modalities

| SDK capability | Agent Kit classification | Agent Kit surface | Rationale / follow-up |
|----------------|--------------------------|-------------------|-----------------------|
| Embeddings | package-owned | `EmbeddingsRuntime`, `AgentKit::embed()` | Package vectors and tools need provider/model override and typed results. |
| Embeddings response cache (`PendingEmbeddingsGeneration::cache()`) | deferred | none | SDK v0.8.1 supports optional Laravel-cache-backed embedding reuse. `EmbeddingsRequest` / `SdkEmbeddingsRuntime` do not expose cache TTL yet. Create a follow-up proposal before wrapping. |
| Image generation | package-owned | `ImageGenerationRuntime`, `AgentKit::generateImage()` | Package facade exposes typed image generation. |
| Audio generation | package-owned | `AudioGenerationRuntime`, `AgentKit::generateAudio()` | Package facade exposes typed audio generation. |
| Transcription | package-owned | `TranscriptionRuntime`, `AgentKit::transcribe()` | Audio blueprints need deterministic runtime contracts. |
| Transcription audio sources | package-owned | `TranscriptionAudioSource`, `TranscriptionRequest::fromAudioSource(...)`, `SdkTranscriptionRuntime` source mapping | SDK supports base64/path/storage/upload constructors; Agent Kit owns source DTOs so applications do not call SDK constructors directly. URL transcription remains fail-closed until SDK/provider support is verified. |
| Reranking | package-owned | `RerankingRuntime`, `AgentKit::rerank()` | Retrieval workflows need typed reranking access. |
| Provider-specific modality options not represented in package DTOs | direct-SDK | none | Use SDK directly when the application needs provider-native knobs that Agent Kit has not promoted. |

## Files, stores, and retrieval

| SDK surface | Agent Kit classification | Agent Kit surface | Rationale / follow-up |
|-------------|--------------------------|-------------------|-----------------------|
| Files gateway | package-owned | `LaravelAiFilesService`, `AgentKit::laravelAiFiles()` | Package adds DTO boundaries and redacted gateway telemetry. |
| Stores gateway | package-owned | `LaravelAiStoresService`, `AgentKit::laravelAiStores()` | Package adds DTO boundaries and redacted gateway telemetry. |
| FileSearch provider tool | package-owned adapter | provider tool names on runtime requests | Authorization and explicit tool selection stay package-owned. |
| WebSearch provider tool | package-owned adapter | provider tool names on runtime requests | Provider-native object construction is hidden behind package aliases. |
| WebFetch provider tool | package-owned adapter | provider tool names on runtime requests | Provider-native object construction is hidden behind package aliases. |
| SDK vector stores / provider-hosted retrieval | direct-SDK | Files/Stores wrappers or direct SDK | Provider-hosted retrieval is distinct from Agent Kit application-owned vectors. |
| Application-owned vector storage | package-owned | `VectorStoreInterface`, `SimilaritySearchTool` | Package owns local/database vector store contracts and fakes. Distinct from SDK `SimilaritySearch` (Eloquent `whereVectorSimilarTo`). |
| SDK Eloquent similarity search tool | direct-SDK | none | SDK `Laravel\Ai\Tools\SimilaritySearch` targets Eloquent models with vector columns. Use directly for SDK-native retrieval; use package `SimilaritySearchTool` for `VectorStoreInterface`. |
| External vector indexes | deferred | custom `VectorStoreInterface` binding | A first-class adapter can be proposed when there is a concrete provider target. |

## Tools

| SDK surface | Agent Kit classification | Agent Kit surface | Rationale / follow-up |
|-------------|--------------------------|-------------------|-----------------------|
| Custom SDK tools | package-owned adapter | package `Tool` contract and `SdkToolMaterializer` | Tool authorization and schema validation must remain package-owned. |
| Provider-native tools | package-owned adapter | `ProviderToolRegistry`, `ProviderToolMaterializer` | Runtime requests name explicit provider-tool aliases. Config supports `web_search`, `web_fetch`, and `file_search` only (matches SDK v0.8.1 `Providers/Tools`). |
| SDK sub-agent tool (`AgentTool`) | direct-SDK | none | SDK wraps a nested `Agent` as an isolated tool. Prefer package orchestration/delegation for multi-agent workflows that need package policy and memory. |
| MCP client/server tools (`McpTool`, `McpServerTool`) | direct-SDK | none | SDK v0.8.1 wraps Laravel MCP client/server tool primitives. No package registry adapter; use SDK tool registration directly or add a proposal if MCP needs package authorization/redaction. |
| Tool input validation | package-owned | `InMemoryToolRegistry` validation | Agent Kit enforces its supported schema subset before handlers run. |
| Tool execution callbacks outside package registry | direct-SDK | none | Use SDK directly for SDK-only experiments that should bypass package tool policy. |

## Blueprints and workflows

| Workflow | Agent Kit classification | Agent Kit surface | Rationale / follow-up |
|----------|--------------------------|-------------------|-----------------------|
| Audio to text to structured text evaluation | package-owned | `AudioToTextToEvaluation` | Package composes transcription plus text evaluation with prompt and schema governance. |
| Audio-image structured evaluation | package-owned | `AudioImageStructuredEvaluation`, `AudioImageStructuredEvaluationPipelineStep`, `AudioImageStructuredEvaluationPipeline` | Package composes transcription plus image/text structured runtime execution for providers that advertise transcription, image input/vision, and structured output. |

## Async and jobs

Use the async inventory for SDK job mapping. Agent Kit queued pipelines are preferred when the workflow needs package budgets, memory, result handlers, retries, and telemetry.

## Events and observability

Use `docs/maintainers/sdk-events-provider-tools-inventory.md` for event and provider-tool mapping. Operationally relevant SDK events should either be normalized into redacted package events or documented as direct SDK event surfaces.

## Testing and fakes

| Agent Kit public surface | Fake / testing path | Classification |
|--------------------------|---------------------|----------------|
| Runtime execution | `FakeAiRuntime`, package assertions | package-owned |
| Transcription runtime | `FakeTranscriptionRuntime`; SDK fakes only for bridge tests | package-owned |
| Streaming runtime | bind `StreamingAiRuntime` test double; SDK bridge tests use Laravel AI fakes | package-owned |
| Provider policy | `FakeProviderPolicy` | package-owned |
| Tools | `FakeToolRunner` and `InMemoryToolRegistry` tests | package-owned |
| Memory | `FakeConversationStore`, in-memory/database/Redis store tests | package-owned |
| Vectors | `FakeVectorStore`, `InMemoryVectorStore` | package-owned |
| Orchestration | `FakeAgentOrchestrator` | package-owned |
| Facade modality methods | bind modality runtime contracts in the container or use SDK fakes in bridge tests | package-owned |
| Laravel AI SDK-only queues/broadcasts | Laravel AI SDK fakes | direct-SDK |

## Maintenance checklist

When upgrading Laravel AI SDK:

1. Run `composer show laravel/ai` and record the installed SDK version in the change or release notes.
2. Scan SDK source for new jobs, gateways, tools, modalities, middleware, and events.
3. Update this matrix, the async inventory, and the events/provider-tool inventory.
4. Classify each new or changed surface as package-owned, direct-SDK, deferred, or out-of-scope.
5. Update public docs only when developer-facing guidance changes.
6. Add package contracts, config, tests, and docs only when a new SDK surface becomes a first-class Agent Kit pattern.
7. Create follow-up OpenSpec proposals for package-owned gaps that are too large for the parity sweep.
