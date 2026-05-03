# Laravel AI SDK ↔ Agent Kit capability matrix

This document maps **Laravel AI SDK** (`laravel/ai`) capabilities to **Laravel AI Agent Kit** entry points. It is the working inventory for the goal: *everything the SDK can do should be reachable through this package’s patterns* (runtime, pipelines, orchestration, budgets, memory) without re‑implementing wiring for each app.

The **`sdk-surface-parity`** roadmap (Phases 1–6) is **complete** as of the package release that ships this matrix revision. Historical design and tasks live under [openspec/changes/archive/2026-05-02-sdk-surface-parity](../openspec/changes/archive/2026-05-02-sdk-surface-parity/proposal.md).

For **async jobs and queue guidance**, see [sdk-async-inventory.md](sdk-async-inventory.md). For production deployment (singletons, queues, vectors), see the **Production checklist** in [README.md](../README.md#production-checklist). Before tagging a release, follow [release-verification.md](release-verification.md).

**SDK version pinned by this package:** `laravel/ai` `^0.6` (see `composer.json`). When the SDK adds gateways or tools, extend this matrix, [sdk-async-inventory.md](sdk-async-inventory.md), and `CHANGELOG.md`.

## Legend

| Status | Meaning |
|--------|--------|
| **Covered** | First-class package API; typical apps should not call the SDK directly for this. |
| **Partial** | Supported for core flows; gaps, escape hatches, or provider variance documented. |
| **Planned** | Intentionally not wrapped yet; listed in [Roadmap](#roadmap-priorities). |
| **Parallel** | SDK feature exists; package offers an **alternative** model (use one pattern, not both blindly). |
| **Escape hatch** | Use `Laravel\Ai\*` or config when you need behavior we have not wrapped. |

---

## Text agents (chat / completion / tools / structured output)

| Laravel AI SDK | Agent Kit | Status | Notes |
|----------------|-------------|--------|-------|
| `AnonymousAgent`, `StructuredAnonymousAgent`, `Agent::prompt()` | `AiRuntime::execute()`, `ExecutionRequest` / `ExecutionResult`, `SdkAiRuntime` | **Covered** | Blueprints and orchestration resolve the same binding. |
| Structured output (`ObjectSchema`, `HasStructuredOutput`, `StructuredAgentResponse`) | `ExecutionRequest::$schema`, `ExecutionResult::$structuredOutput` | **Covered** | Specialist blueprints prefer structured output; see README and blueprint tests. |
| Streaming (`StreamedAgentResponse`, stream events) | `StreamingAiRuntime::executeStream()`, `StreamChunk` / `StreamComplete` / `StreamFailure`; also `AgentKit::executeStream()` | **Covered** | No streaming for schema-backed calls; use `execute()`. |
| Application convenience | `AgentKit` facade / `AgentKitManager` (`executeStream`, `embed`, `transcribe`, `generateImage`, `rerank`, `generateAudio`, `laravelAiFiles`, `laravelAiStores`) | **Covered** | Thin `app()` delegates; prefer injecting contracts in long-lived services. |
| Provider tools: `WebSearch`, `WebFetch`, `FileSearch` | `provider_tool_names` on `ExecutionRequest`; provider tool factories in `LaravelAiAgentKitServiceProvider` | **Covered** | Names must match SDK tool registration. |
| Custom `Laravel\Ai\Contracts\Tool` | Package `Tool` + `SdkToolAdapter` / authorizer | **Covered** | See tool registry and governance docs. |
| SDK agent middleware (e.g. `RememberConversation`) | Package `RuntimeConversationMemoryBridge`, `ConversationStore`, `RunContext` | **Parallel** | Different persistence model; package memory + optional legacy read bridge replaces ad-hoc SDK conversation middleware for kit-driven flows. |
| Fake agents / fake gateways (`Ai::fake`, per-agent fakes) | Package fakes (`FakeAiRuntime`, etc.) | **Partial** | Use SDK fakes when testing raw SDK; use package fakes for kit contracts. |

---

## Modalities (non-chat completions)

| Laravel AI SDK | Agent Kit | Status | Notes |
|----------------|-------------|--------|-------|
| `Embeddings::` | `EmbeddingsRuntime` (`SdkEmbeddingsRuntime`) | **Covered** | Config: `ai-agent-kit.modalities.embeddings`. |
| `Image::` | `ImageGenerationRuntime` (`SdkImageGenerationRuntime`) | **Covered** | Config: `modalities.image_generation`. |
| `Transcription::` | `TranscriptionRuntime` (`SdkTranscriptionRuntime`) | **Covered** | Used by audio blueprint paths; config: `modalities.transcription`. |
| `Reranking::` | `RerankingRuntime` (`SdkRerankingRuntime`) | **Covered** | Config: `modalities.reranking`; provider must support reranking (e.g. Cohere). |
| `Audio::` (TTS / **audio generation**) | `AudioGenerationRuntime` (`SdkAudioGenerationRuntime`) | **Covered** | Config: `modalities.audio_generation`. |

---

## Files and provider stores (uploads + vector stores)

| Laravel AI SDK | Agent Kit | Status | Notes |
|----------------|-------------|--------|-------|
| `Files::get`, `Files::put`, `Files::delete`, … | `LaravelAiFilesService` | **Covered** | DTOs: `StoredProviderFile`, `ProviderFileContents`. Optional `laravel_ai_files.default_provider`. Redacted events: `LaravelAiFilesGatewayOperationFinished` (toggle `observability.laravel_ai_files_stores.enabled`). |
| `Stores::create`, `Stores::get`, `Store::add`, … (provider **vector stores** for RAG) | `LaravelAiStoresService` | **Covered** | Provider store lifecycle and file references for RAG. Redacted events: `LaravelAiStoresGatewayOperationFinished`. Combine with **`FileSearch`** on `ExecutionRequest` and optional app **`VectorStoreInterface`** for embeddings you own (see below). |
| `FileSearch` provider tool | Wired via provider tool factories + `provider_tool_names` | **Covered** | Requires store IDs / provider configuration in Laravel AI config. |
| Application-owned embeddings + similarity | `VectorStoreInterface` (`in_memory`, **`database`**) + optional **`SimilaritySearchTool`** | **Covered** | **Distinct** from provider Stores: app stores embeddings in SQL or memory, enforces **one embedding width per namespace** on `upsert`, dot-product search in PHP (**O(n)** per namespace; optional `max_scan_rows`). |

---

## Retrieval and tools (application-side)

| Laravel AI SDK | Agent Kit | Status | Notes |
|----------------|-------------|--------|-------|
| `Laravel\Ai\Tools\SimilaritySearch` (Eloquent / DB vector similarity) | Same SDK class for `whereVectorSimilarTo` models | **Escape hatch** | Use when your source of truth is **Eloquent/pgvector**. For **`VectorStoreInterface`**, use **`SimilaritySearchTool`** (`tools.similarity_search.*`, `ToolAuthorizer`). |

---

## Queuing and async

| Laravel AI SDK | Agent Kit | Status | Notes |
|----------------|-------------|--------|-------|
| `InvokeAgent`, `GenerateImage`, `GenerateEmbeddings`, `GenerateTranscription`, `GenerateAudio`, `BroadcastAgent` jobs | `QueuedPipelineDispatcher` + docs inventory | **Parallel** | See [sdk-async-inventory.md](sdk-async-inventory.md): prefer kit **pipelines** for structured agent runs; use SDK **jobs** when you need Laravel AI’s serialized payloads or `BroadcastAgent` specifically. |
| `QueuedAgentPrompt` | Pipelines + `RunContext` | **Parallel** | Prefer modeling work as a **queued pipeline** for kit budgets and observability; see inventory. |

---

## Events and observability

| Laravel AI SDK | Agent Kit | Status | Notes |
|----------------|-------------|--------|-------|
| `AgentPrompted`, `ToolInvoked`, … | `SdkTelemetryNormalizer`, redacted package runtime events | **Covered** | Kit listens and emits redacted domain events for runtime/tool flows. |
| Files / Stores gateway (via package services) | `LaravelAiFilesGatewayOperationFinished`, `LaravelAiStoresGatewayOperationFinished` | **Covered** | Redacted operation summaries only; disable in tests via `observability.laravel_ai_files_stores.enabled` if needed. |

---

## Roadmap priorities (completed: `sdk-surface-parity`)

The following items shipped in dependency order as OpenSpec change **`sdk-surface-parity`** (archived under [openspec/changes/archive/2026-05-02-sdk-surface-parity](../openspec/changes/archive/2026-05-02-sdk-surface-parity/proposal.md)):

1. **`Audio` generation** — `AudioGenerationRuntime` + `SdkAudioGenerationRuntime`; `modalities.audio_generation`.
2. **Provider `Files` + `Stores`** — `LaravelAiFilesService` / `LaravelAiStoresService`; `laravel_ai_files.default_provider`, `laravel_ai_stores.default_provider`.
3. **Vector / retrieval parity** — `vector.default_driver` = `in_memory` | **`database`** (`DatabaseVectorStore`, `create_ai_agent_vector_documents_table` migration stub). Distinct from Laravel AI **Stores** (provider-hosted RAG file stores).
4. **Packaged similarity search** — **`SimilaritySearchTool`** + `tools.similarity_search` (opt-in). Laravel AI **`SimilaritySearch`** remains for Eloquent `whereVectorSimilarTo` flows.
5. **Facade ergonomics** — **`AgentKit` / `AgentKitManager`**: `executeStream`, modality delegates, `laravelAiFiles()`, `laravelAiStores()`.

---

## Maintenance

When upgrading `laravel/ai`:

1. Scan `vendor/laravel/ai/src` for new facades, jobs, and provider tools.
2. Update this matrix, [sdk-async-inventory.md](sdk-async-inventory.md), and `CHANGELOG.md`.
3. Run [release-verification.md](release-verification.md).
4. If supported **PHP** or **Laravel** lines change, update [github-ci-matrix.md](github-ci-matrix.md) and `.github/workflows/ci.yml` together.
5. Prefer adding **contracts + config + tests** in Agent Kit when a new SDK surface should become a first-class kit pattern.
