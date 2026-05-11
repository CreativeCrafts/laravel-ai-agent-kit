# SDK events and provider tools inventory

This maintainer reference maps Laravel AI SDK events and provider-native tools to Agent Kit observability and tool-governance decisions. Keep it current when upgrading `laravel/ai`.

## Audit metadata

| Field | Value |
|-------|-------|
| Audit target | `laravel/ai ^0.6` from `composer.json` |
| Lockfile status | This package repository does not commit `composer.lock`; release validation should record the locally installed `laravel/ai` patch version with `composer show laravel/ai`. |
| Last parity sweep | `audit-laravel-ai-sdk-parity` |
| Classification values | `package-normalized`, `package-owned-adapter`, `direct-SDK`, `deferred`, `out-of-scope` |

## Event inventory

| Laravel AI SDK event surface | Agent Kit classification | Agent Kit surface | Notes |
|------------------------------|--------------------------|-------------------|-------|
| Agent prompting lifecycle | package-normalized | `SdkTelemetryNormalizer` and redacted runtime events | Agent Kit listens to SDK prompt lifecycle events and emits package-safe telemetry where operationally useful. |
| Agent prompted/completed lifecycle | package-normalized | `SdkTelemetryNormalizer` and runtime result metadata | Package events must avoid raw prompt bodies, generated text, API keys, file bodies, and tool payloads. |
| Tool invocation start | package-normalized | `SdkTelemetryNormalizer` and package tool telemetry | Tool names and safe metadata are allowed; raw tool payloads are not. |
| Tool invocation completed | package-normalized | `SdkTelemetryNormalizer` and package tool telemetry | Keep authorization and schema validation package-owned. |
| Streaming chunk/completion/failure events | package-normalized | `RuntimeStreamChunkEmitted`, `RuntimeStreamCompleted`, `RuntimeStreamFailed` | Agent Kit emits package stream lifecycle events from `StreamingAiRuntime`. |
| Modality-specific SDK events | direct-SDK / deferred | modality runtime DTOs; no broad package event wrapper yet | Use direct SDK listeners if the application needs SDK-native modality events. Add a proposal before normalizing high-value modality events. |
| Files gateway events | package-normalized | `LaravelAiFilesGatewayOperationFinished` | Agent Kit wrappers emit redacted file gateway operation events. |
| Stores gateway events | package-normalized | `LaravelAiStoresGatewayOperationFinished` | Agent Kit wrappers emit redacted store gateway operation events. |
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
| SDK job lifecycle normalization | direct-SDK | Keep as Laravel queue/SDK event handling unless package queued pipelines need a new event. |
| New SDK provider-native tools | deferred | Classify per tool during SDK upgrade audit. |

## Maintenance process

1. Scan SDK source for `Events` and provider `Tools` after each `laravel/ai` upgrade.
2. Update this inventory with new or changed events/tools.
3. Classify each new surface as package-normalized, package-owned-adapter, direct-SDK, deferred, or out-of-scope.
4. Add package events or provider-tool adapters only when the surface needs package policy, redaction, authorization, or stable package tests.
5. Update public docs when developer-facing usage guidance changes.
