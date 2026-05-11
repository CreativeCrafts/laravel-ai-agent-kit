# SDK capability matrix

This maintainer reference maps Laravel AI SDK surfaces to Agent Kit entry points. Keep it current when upgrading `laravel/ai` or adding package wrappers around SDK capabilities.

## Audit metadata

| Field | Value |
|-------|-------|
| Audit target | `laravel/ai ^0.6` from `composer.json` |
| Lockfile status | This package repository does not commit `composer.lock`; release validation should record the locally installed `laravel/ai` patch version with `composer show laravel/ai`. |
| Last parity sweep | `audit-laravel-ai-sdk-parity` |
| Classification values | `package-owned`, `direct-SDK`, `deferred`, `out-of-scope` |

## Principle

Laravel AI SDK is the internal execution substrate. Agent Kit owns workflow composition, provider policy, prompt governance, tool governance, memory policy, redacted telemetry, and package-owned public contracts.

Agent Kit does not mirror every SDK class. If an SDK surface does not need package policy, memory, workflow composition, telemetry normalization, or fake support, direct SDK usage is acceptable and should be documented.

## Runtime and agents

| Laravel AI SDK surface | Agent Kit classification | Agent Kit surface | Rationale / follow-up |
|------------------------|--------------------------|-------------------|-----------------------|
| Anonymous/text agents | package-owned | `AiRuntime`, `ExecutionRequest`, `ExecutionResult`, `AgentKit::run()` | Runtime calls need provider policy, memory projection, tools, budgets, telemetry, and package DTOs. |
| Structured output | package-owned | `ExecutionRequest::$schema`, `ExecutionResult::$structuredOutput` | Schema-backed execution is part of package blueprint/runtime behavior. |
| Streaming text | package-owned | `StreamingAiRuntime`, `AgentKit::executeStream()` | Streaming needs package failure normalization, lifecycle telemetry, and conservative failover semantics. |
| SDK broadcast agent jobs | direct-SDK | none | Use SDK jobs directly when the application wants SDK-specific broadcast-channel behavior instead of package runtime streaming. |
| Generation options | package-owned | `GenerationOptions` on `ExecutionRequest` | Package runtime preserves options across provider attempts. |
| SDK middleware / agent prompt internals | out-of-scope | package memory bridge and runtime middleware | Agent Kit owns runtime middleware and memory behavior rather than exposing SDK internals. |
| Conversation context | package-owned | `ConversationStore`, `RuntimeConversationMemoryBridge` | Memory retention, encryption, and replay policy are package concerns. |
| Provider selection | package-owned | provider profiles, `ProviderSelector`, `FailoverProviderSelector` | Package workflows reason over provider profiles and capability names. |
| Provider failover execution | package-owned | `SdkAiRuntime` failover loop | Runtime retries provider-edge failures through configured failover order. |

## Modalities

| SDK capability | Agent Kit classification | Agent Kit surface | Rationale / follow-up |
|----------------|--------------------------|-------------------|-----------------------|
| Embeddings | package-owned | `EmbeddingsRuntime`, `AgentKit::embed()` | Package vectors and tools need provider/model override and typed results. |
| Embedding query/cache helpers | deferred | none | Useful if SDK exposes stable query/cache primitives that should be policy-governed. Create a follow-up proposal before wrapping. |
| Image generation | package-owned | `ImageGenerationRuntime`, `AgentKit::generateImage()` | Package facade exposes typed image generation. |
| Audio generation | package-owned | `AudioGenerationRuntime`, `AgentKit::generateAudio()` | Package facade exposes typed audio generation. |
| Transcription | package-owned | `TranscriptionRuntime`, `AgentKit::transcribe()` | Audio blueprints need deterministic runtime contracts. |
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
| Application-owned vector storage | package-owned | `VectorStoreInterface`, `SimilaritySearchTool` | Package owns local/database vector store contracts and fakes. |
| External vector indexes | deferred | custom `VectorStoreInterface` binding | A first-class adapter can be proposed when there is a concrete provider target. |

## Tools

| SDK surface | Agent Kit classification | Agent Kit surface | Rationale / follow-up |
|-------------|--------------------------|-------------------|-----------------------|
| Custom SDK tools | package-owned adapter | package `Tool` contract and `SdkToolMaterializer` | Tool authorization and schema validation must remain package-owned. |
| Provider-native tools | package-owned adapter | `ProviderToolRegistry`, `ProviderToolMaterializer` | Runtime requests name explicit provider-tool aliases. |
| Tool input validation | package-owned | `InMemoryToolRegistry` validation | Agent Kit enforces its supported schema subset before handlers run. |
| Tool execution callbacks outside package registry | direct-SDK | none | Use SDK directly for SDK-only experiments that should bypass package tool policy. |

## Async and jobs

Use the async inventory for SDK job mapping. Agent Kit queued pipelines are preferred when the workflow needs package budgets, memory, result handlers, retries, and telemetry.

## Events and observability

Use `docs/maintainers/sdk-events-provider-tools-inventory.md` for event and provider-tool mapping. Operationally relevant SDK events should either be normalized into redacted package events or documented as direct SDK event surfaces.

## Testing and fakes

| Agent Kit public surface | Fake / testing path | Classification |
|--------------------------|---------------------|----------------|
| Runtime execution | `FakeAiRuntime`, package assertions | package-owned |
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
