# SDK events and provider tools inventory

This maintainer reference maps Laravel AI SDK events and provider-native tools to Agent Kit observability and tool-governance decisions. Keep it current when upgrading `laravel/ai`.

## Audit metadata

| Field | Value |
|-------|-------|
| Audit target | `laravel/ai ^0.8` from `composer.json` (CI lockfile currently resolves `v0.8.1`) |
| Lockfile status | This repository commits `composer.lock`. Record the resolved `laravel/ai` patch with `composer show laravel/ai` when updating maintainer inventories. |
| Resolved SDK version | `v0.8.1` (`composer show laravel/ai`, 2026-06-30) |
| Last parity sweep | 2026-06-30 — scan of `vendor/laravel/ai/src/Events`, `Streaming/Events`, and `Providers/Tools` |
| Classification values | `package-normalized`, `package-owned-adapter`, `direct-SDK`, `deferred`, `out-of-scope` |

## Event inventory

| Laravel AI SDK event surface | Agent Kit classification | Agent Kit surface | Notes |
|------------------------------|--------------------------|-------------------|-------|
| Agent prompting lifecycle | package-normalized | `SdkTelemetryNormalizer` and redacted runtime events | Agent Kit listens to SDK prompt lifecycle events and emits package-safe telemetry where operationally useful. |
| Agent prompted/completed lifecycle | package-normalized | `SdkTelemetryNormalizer` and runtime result metadata | Package events must avoid raw prompt bodies, generated text, API keys, file bodies, and tool payloads. |
| Tool invocation start | package-normalized | `SdkTelemetryNormalizer` and package tool telemetry | Tool names and safe metadata are allowed; raw tool payloads are not. |
| Tool invocation completed | package-normalized | `SdkTelemetryNormalizer` and package tool telemetry | Keep authorization and schema validation package-owned. |
| Streaming text delta chunks | package-normalized | `RuntimeStreamChunkEmitted` (`type: text_delta` only) | `SdkAiRuntime` forwards SDK `TextDelta` events into package stream chunks and redacted chunk telemetry. |
| Streaming reasoning, citation, and in-stream tool events | deferred | none | SDK v0.8.1 emits `ReasoningStart`, `ReasoningDelta`, `ReasoningEnd`, `Citation`, `ProviderToolEvent`, `ToolCall`, and `ToolResult`. Not normalized into package events yet. Use SDK stream listeners or extend runtime if needed. |
| Streaming completion/failure lifecycle | package-normalized | `RuntimeStreamCompleted`, `RuntimeStreamFailed` | Agent Kit emits package stream terminal events from `SdkAiRuntime`. SDK `StreamingAgent` / `AgentStreamed` remain direct-SDK surfaces. |
| Provider/agent failover events | direct-SDK | none | SDK emits `AgentFailedOver` and `ProviderFailedOver`. Package failover is implemented in `SdkAiRuntime` without listening to these events. |
| Modality-specific SDK events | direct-SDK / deferred | modality runtime DTOs; no broad package event wrapper yet | Use direct SDK listeners if the application needs SDK-native modality events. Add a proposal before normalizing high-value modality events. |
| Files gateway operations (via package wrappers) | package-normalized | `LaravelAiFilesGatewayOperationFinished` | `LaravelAiFilesService` emits redacted package events. Raw SDK file lifecycle events remain direct-SDK (see appendix). |
| Stores gateway operations (via package wrappers) | package-normalized | `LaravelAiStoresGatewayOperationFinished` | `LaravelAiStoresService` emits redacted package events. Raw SDK store lifecycle events remain direct-SDK (see appendix). |
| SDK queue job lifecycle events | direct-SDK | none | Laravel queue/job events remain Laravel/SDK surfaces; package queued pipelines own package result handling. |
| Provider-native low-level HTTP/client events | out-of-scope | none | Use provider SDK/client observability directly outside Agent Kit. |

## Provider tool inventory

| Laravel AI SDK provider tool | Agent Kit classification | Agent Kit surface | Notes |
|------------------------------|--------------------------|-------------------|-------|
| `WebSearch` | package-owned-adapter | `tools.provider_tools.*` alias, `ProviderToolRegistry`, `ProviderToolMaterializer` | Runtime requests opt into provider tools by alias; `ToolAuthorizer::authorizeProviderTool()` remains authoritative. |
| `WebFetch` | package-owned-adapter | `tools.provider_tools.*` alias, `ProviderToolRegistry`, `ProviderToolMaterializer` | Allowed domains are configured in package config, not request payloads. |
| `FileSearch` | package-owned-adapter | `tools.provider_tools.*` alias, `ProviderToolRegistry`, `ProviderToolMaterializer` | Provider-hosted stores remain separate from `VectorStoreInterface`. |
| New provider-native tools introduced by SDK upgrades | deferred until classified | none | Classify during the SDK parity sweep. Add a package adapter only if policy, authorization, docs, and tests are needed. |
| Provider-specific experimental tools | direct-SDK | none | Use Laravel AI SDK directly for provider experiments that should not become stable Agent Kit API. |

## Custom SDK tool inventory

SDK tools outside provider-native `Providers/Tools` that are not adapted by Agent Kit:

| Laravel AI SDK tool | Agent Kit classification | Agent Kit surface | Notes |
|---------------------|--------------------------|-------------------|-------|
| `Laravel\\Ai\\Tools\\SimilaritySearch` | direct-SDK | none | Eloquent vector similarity helper. Package-owned retrieval uses `SimilaritySearchTool` + `VectorStoreInterface`. |
| `Laravel\\Ai\\Tools\\AgentTool` | direct-SDK | none | Sub-agent-as-tool pattern. Package multi-agent flows use orchestration/delegation instead. |
| `Laravel\\Ai\\Tools\\McpTool` | direct-SDK | none | Wraps MCP client tool primitives (`mcp_tools_*` name prefix). |
| `Laravel\\Ai\\Tools\\McpServerTool` | direct-SDK | none | Wraps MCP server tool classes. |

Package custom tools registered through `InMemoryToolRegistry` are materialized via `SdkToolMaterializer` / `SdkToolAdapter` regardless of SDK tool type.

## SDK event class appendix (v0.8.1)

Use this appendix during parity sweeps. Class names are under `Laravel\Ai\Events\` unless noted.

| SDK event class | Agent Kit classification | Normalized by / notes |
|-----------------|--------------------------|------------------------|
| `PromptingAgent` | package-normalized | `SdkTelemetryNormalizer` → `RuntimeExecutionStarted` |
| `AgentPrompted` | package-normalized | `SdkTelemetryNormalizer` → `RuntimeExecutionCompleted` |
| `InvokingTool` | package-normalized | `SdkTelemetryNormalizer` → `RuntimeToolInvocationStarted` |
| `ToolInvoked` | package-normalized | `SdkTelemetryNormalizer` → `RuntimeToolInvocationCompleted` |
| `StreamingAgent` | direct-SDK | Package stream lifecycle uses `RuntimeStream*` events instead |
| `AgentStreamed` | direct-SDK | Same as above |
| `AgentFailedOver` | direct-SDK | Package failover loop in `SdkAiRuntime` |
| `ProviderFailedOver` | direct-SDK | Same as above |
| `GeneratingEmbeddings` | direct-SDK | Use modality DTOs / SDK listeners |
| `EmbeddingsGenerated` | direct-SDK | Same |
| `GeneratingImage` | direct-SDK | Same |
| `ImageGenerated` | direct-SDK | Same |
| `GeneratingAudio` | direct-SDK | Same |
| `AudioGenerated` | direct-SDK | Same |
| `GeneratingTranscription` | direct-SDK | Same |
| `TranscriptionGenerated` | direct-SDK | Same |
| `Reranking` | direct-SDK | Same |
| `Reranked` | direct-SDK | Same |
| `StoringFile` | direct-SDK | Prefer `LaravelAiFilesGatewayOperationFinished` for wrapped gateway calls |
| `FileStored` | direct-SDK | Same |
| `FileDeleted` | direct-SDK | Same |
| `CreatingStore` | direct-SDK | Prefer `LaravelAiStoresGatewayOperationFinished` for wrapped gateway calls |
| `StoreCreated` | direct-SDK | Same |
| `StoreDeleted` | direct-SDK | Same |
| `AddingFileToStore` | direct-SDK | Same |
| `FileAddedToStore` | direct-SDK | Same |
| `RemovingFileFromStore` | direct-SDK | Same |
| `FileRemovedFromStore` | direct-SDK | Same |

Stream event classes under `Laravel\Ai\Streaming\Events\` (v0.8.1):

| SDK stream event class | Agent Kit classification | Normalized by / notes |
|------------------------|--------------------------|------------------------|
| `TextDelta` | package-normalized (partial) | Forwarded to package `StreamChunk` / `RuntimeStreamChunkEmitted` |
| `TextStart`, `TextEnd` | deferred | Not forwarded by package runtime |
| `ReasoningStart`, `ReasoningDelta`, `ReasoningEnd` | deferred | Not forwarded |
| `Citation` | deferred | Not forwarded |
| `ProviderToolEvent` | deferred | Not forwarded |
| `ToolCall`, `ToolResult` | deferred | Not forwarded |
| `StreamStart`, `StreamEnd` | direct-SDK | `StreamStart` meta used internally; not emitted as package events |
| `Error` | package-normalized (partial) | Mapped to package stream failure handling |

## Redaction rules

Package-normalized events must not include:

- raw prompts
- generated completions
- file bodies
- attachment payloads
- provider API keys or tokens
- raw tool input/output payloads
- unredacted metadata values when only keys are needed

Safe operational fields include:

- run IDs
- provider/profile names
- model names
- tool names
- counts and byte lengths
- metadata keys after redaction
- package conversation IDs
- failure categories and exception classes

## Follow-up decision log

| Gap | Classification | Follow-up |
|-----|----------------|-----------|
| Broad modality event normalization | deferred | Create an OpenSpec proposal only if applications need package-redacted modality telemetry beyond returned DTOs. |
| Streaming reasoning/citation/tool stream events | deferred | Extend `StreamChunk` and stream telemetry if applications need package-first access without SDK listeners. |
| MCP tool adapters | direct-SDK | Add package registry/config only if MCP tools need authorization, redaction, or stable package tests. |
| SDK job lifecycle normalization | direct-SDK | Keep as Laravel queue/SDK event handling unless package queued pipelines need a new event. |
| SDK failover event normalization | direct-SDK | Package failover remains in `SdkAiRuntime`; normalize only if operators need unified failover telemetry. |
| New SDK provider-native tools | deferred | Classify per tool during SDK upgrade audit. |

## Maintenance process

1. Scan SDK source for `Events` and provider `Tools` after each `laravel/ai` upgrade.
2. Update this inventory with new or changed events/tools.
3. Classify each new surface as package-normalized, package-owned-adapter, direct-SDK, deferred, or out-of-scope.
4. Add package events or provider-tool adapters only when the surface needs package policy, redaction, authorization, or stable package tests.
5. Update public docs when developer-facing usage guidance changes.
