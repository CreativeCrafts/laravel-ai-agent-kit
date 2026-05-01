# Laravel AI SDK ↔ Agent Kit capability matrix

This document maps **Laravel AI SDK** (`laravel/ai`) capabilities to **Laravel AI Agent Kit** entry points. It is the working inventory for the goal: *everything the SDK can do should be reachable through this package’s patterns* (runtime, pipelines, orchestration, budgets, memory) without re‑implementing wiring for each app.

**SDK version pinned by this package:** `laravel/ai` `^0.6` (see `composer.json`). When the SDK adds gateways or tools, extend this matrix and the roadmap below.

## Legend

| Status | Meaning |
|--------|---------|
| **Covered** | First-class package API; typical apps should not call the SDK directly for this. |
| **Partial** | Supported for core flows; gaps, escape hatches, or provider variance documented. |
| **Planned** | Intentionally not wrapped yet; listed in [Roadmap](#roadmap-priorities). |
| **Parallel** | SDK feature exists; package offers an **alternative** model (use one pattern, not both blindly). |
| **Escape hatch** | Use `Laravel\Ai\*` or config when you need behavior we have not wrapped. |

---

## Text agents (chat / completion / tools / structured output)

| Laravel AI SDK | Agent Kit | Status | Notes |
|----------------|-------------|--------|--------|
| `AnonymousAgent`, `StructuredAnonymousAgent`, `Agent::prompt()` | `AiRuntime::execute()`, `ExecutionRequest` / `ExecutionResult`, `SdkAiRuntime` | **Covered** | Blueprints and orchestration resolve the same binding. |
| Structured output (`ObjectSchema`, `HasStructuredOutput`, `StructuredAgentResponse`) | `ExecutionRequest::$schema`, `ExecutionResult::$structuredOutput` | **Covered** | Specialist blueprints prefer structured output; see `UPGRADE.md`. |
| Streaming (`StreamedAgentResponse`, stream events) | `StreamingAiRuntime::executeStream()`, `StreamChunk` / `StreamComplete` / `StreamFailure` | **Covered** | No streaming for schema-backed calls; use `execute()`. |
| Provider tools: `WebSearch`, `WebFetch`, `FileSearch` | `provider_tool_names` on `ExecutionRequest`; provider tool factories in `LaravelAiAgentKitServiceProvider` | **Covered** | Names must match SDK tool registration. |
| Custom `Laravel\Ai\Contracts\Tool` | Package `Tool` + `SdkToolAdapter` / authorizer | **Covered** | See tool registry and governance docs. |
| SDK agent middleware (e.g. `RememberConversation`) | Package `RuntimeConversationMemoryBridge`, `ConversationStore`, `RunContext` | **Parallel** | Different persistence model; package memory + optional legacy read bridge replaces ad-hoc SDK conversation middleware for kit-driven flows. |
| Fake agents / fake gateways (`Ai::fake`, per-agent fakes) | Package fakes (`FakeAiRuntime`, etc.) | **Partial** | Use SDK fakes when testing raw SDK; use package fakes for kit contracts. |

---

## Modalities (non-chat completions)

| Laravel AI SDK | Agent Kit | Status | Notes |
|----------------|-------------|--------|--------|
| `Embeddings::` | `EmbeddingsRuntime` (`SdkEmbeddingsRuntime`) | **Covered** | Config: `ai-agent-kit.modalities.embeddings`. |
| `Image::` | `ImageGenerationRuntime` (`SdkImageGenerationRuntime`) | **Covered** | Config: `modalities.image_generation`. |
| `Transcription::` | `TranscriptionRuntime` (`SdkTranscriptionRuntime`) | **Covered** | Used by audio blueprint paths; config: `modalities.transcription`. |
| `Reranking::` | `RerankingRuntime` (`SdkRerankingRuntime`) | **Covered** | Config: `modalities.reranking`; provider must support reranking (e.g. Cohere). |
| `Audio::` (TTS / **audio generation**) | `AudioGenerationRuntime` (`SdkAudioGenerationRuntime`) | **Covered** | Config: `modalities.audio_generation`. |

---

## Files and provider stores (uploads + vector stores)

| Laravel AI SDK | Agent Kit | Status | Notes |
|----------------|-------------|--------|--------|
| `Files::get`, `Files::put`, `Files::delete`, … | `LaravelAiFilesService` | **Covered** | DTOs: `StoredProviderFile`, `ProviderFileContents`. Optional `laravel_ai_files.default_provider`. |
| `Stores::create`, `Stores::get`, `Store::add`, … (provider **vector stores** for RAG) | `LaravelAiStoresService` + `VectorStoreInterface` (`in_memory`, **`database`**) | **Partial** | **Stores API** wrapped for provider RAG workflows. **`VectorStoreInterface`** is app-owned embeddings + cosine search (distinct from OpenAI vector stores). |
| `FileSearch` provider tool | Wired via provider tool factories + `provider_tool_names` | **Covered** | Requires store IDs / provider configuration in Laravel AI config. |

---

## Retrieval and tools (application-side)

| Laravel AI SDK | Agent Kit | Status | Notes |
|----------------|-------------|--------|--------|
| `Laravel\Ai\Tools\SimilaritySearch` (Eloquent / DB vector similarity) | — | **Planned** | Not exposed as a packaged tool; apps can register custom tools or use `VectorStoreInterface`. |

---

## Queuing and async

| Laravel AI SDK | Agent Kit | Status | Notes |
|----------------|-------------|--------|--------|
| `InvokeAgent`, `GenerateImage`, `GenerateEmbeddings`, `GenerateTranscription`, `GenerateAudio`, `BroadcastAgent` jobs | `QueuedPipelineDispatcher`, pipeline definitions, queue options | **Parallel** | Package **pipeline** queue is the structured model; SDK jobs remain available for SDK-only workflows. |
| `QueuedAgentPrompt` | — | **Escape hatch** | Prefer kit pipelines for cross-cutting budgets and observability. |

---

## Events and observability

| Laravel AI SDK | Agent Kit | Status | Notes |
|----------------|-------------|--------|--------|
| `AgentPrompted`, `ToolInvoked`, … | `SdkTelemetryNormalizer`, redacted package events | **Partial** | Kit listens to SDK events where useful; primary story is package domain events. |

---

## Roadmap priorities

**OpenSpec program:** [openspec/changes/sdk-surface-parity](../openspec/changes/sdk-surface-parity/proposal.md) — `proposal.md`, `design.md`, `tasks.md`, and delta specs under `specs/` track implementation of the items below.

Ordered for **coverage without breaking** the single runtime story:

1. **`Audio` generation** — Add an `AudioGenerationRuntime` (or equivalent) and config under `modalities`, aligned with `SdkTranscriptionRuntime` patterns.
2. **Provider `Files` + `Stores`** — **`LaravelAiFilesService`** / **`LaravelAiStoresService`** with config `laravel_ai_files.default_provider` and `laravel_ai_stores.default_provider` (Phase 3 shipped).
3. **Vector / retrieval parity** — `ai-agent-kit.vector.default_driver` supports `in_memory` and **`database`** (publish `create_ai_agent_vector_documents_table` migration). Distinct from Laravel AI **Stores** (provider-hosted file stores for RAG).
4. **Optional packaged `SimilaritySearch`-style tool** — Only if it fits the tool authorizer model; otherwise document custom-tool registration.
5. **Facade ergonomics** — e.g. `AgentKit` helpers for streaming and modalities once contracts are stable (optional product decision).

---

## Maintenance

When upgrading `laravel/ai`:

1. Scan `vendor/laravel/ai/src` for new facades (`*::` static entry points), jobs, and provider tools.
2. Update this matrix and `CHANGELOG.md`.
3. Prefer adding **contracts + config + tests** in Agent Kit over documenting “use the SDK” long term.
