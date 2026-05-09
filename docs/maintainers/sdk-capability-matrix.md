# SDK capability matrix

This maintainer reference maps Laravel AI SDK surfaces to Agent Kit entry points. Keep it current when upgrading `laravel/ai` or adding package wrappers around SDK capabilities.

## Principle

Laravel AI SDK is the internal execution substrate. Agent Kit owns workflow composition, provider policy, prompt governance, tool governance, memory policy, redacted telemetry, and package-owned public contracts.

## Text agents

| Laravel AI SDK surface | Agent Kit surface | Guidance |
|------------------------|-------------------|----------|
| Anonymous or structured agents | `AiRuntime`, `ExecutionRequest`, `ExecutionResult` | Covered through package runtime contracts. |
| Structured output | package schema-backed execution | Keep result DTOs package-owned. |
| Streaming | `StreamingAiRuntime` | Streaming is for non-schema calls. |
| Provider tools | provider tool names on package requests | Do not expose SDK tool objects as package public APIs. |
| Custom tools | package `Tool` contracts and SDK adapter | Package authorization remains authoritative. |
| SDK conversation middleware | package memory bridge and stores | Package memory remains authoritative for Agent Kit workflows. |

## Modalities

| SDK capability | Agent Kit surface |
|----------------|-------------------|
| Embeddings | `EmbeddingsRuntime` |
| Image generation | `ImageGenerationRuntime` |
| Transcription | `TranscriptionRuntime` |
| Reranking | `RerankingRuntime` |
| Audio generation | `AudioGenerationRuntime` |

## Files, stores, and retrieval

| SDK surface | Agent Kit guidance |
|-------------|--------------------|
| Files | `LaravelAiFilesService` |
| Stores | `LaravelAiStoresService` |
| FileSearch | provider tool names on package requests |
| Application vectors | `VectorStoreInterface` and optional `SimilaritySearchTool` |

Provider-hosted stores and application-owned vectors are separate retrieval surfaces. Keep that boundary explicit in public docs.

## Queues and async

Use the async inventory for SDK job mapping. Agent Kit queued pipelines are preferred when the workflow needs package budgets, memory, result handlers, and telemetry.

## Maintenance checklist

When upgrading Laravel AI SDK:

1. Scan SDK source for new jobs, gateways, tools, modalities, and events.
2. Update this matrix and the async inventory.
3. Update public docs only when developer-facing guidance changes.
4. Update `CHANGELOG.md` for notable changes.
5. Add package contracts, config, tests, and docs when a new SDK surface becomes a first-class Agent Kit pattern.
