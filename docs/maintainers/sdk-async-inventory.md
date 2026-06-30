# SDK async inventory

This maintainer reference maps Laravel AI SDK queue-oriented entry points to Agent Kit guidance. Update it when upgrading `laravel/ai`.

## Audit metadata

| Field | Value |
|-------|-------|
| Audit target | `laravel/ai ^0.8` from `composer.json` (CI lockfile currently resolves `v0.8.1`) |
| Lockfile status | This repository commits `composer.lock`. Record the resolved `laravel/ai` patch with `composer show laravel/ai` when updating maintainer inventories. |
| Resolved SDK version | `v0.8.1` (`composer show laravel/ai`, 2026-06-30) |
| Last parity sweep | 2026-06-30 — scan of `vendor/laravel/ai/src/Jobs`; no new job classes beyond the inventory below |

## Recommendation legend

| Recommendation | Meaning |
|----------------|---------|
| Pipeline | Prefer `QueuedPipelineDispatcher`, `RunQueuedPipelineJob`, and `RunContext` for structured package workflows. |
| Runtime | Prefer package runtime or modality contracts inside application-owned jobs or pipeline steps. |
| SDK job | Use the Laravel AI SDK job directly when the app intentionally wants the SDK queue contract. |
| Out of scope | Do not wrap unless a later proposal identifies a package-owned policy/telemetry/workflow need. |

## SDK jobs

| SDK job | Purpose | Classification | Agent Kit recommendation |
|---------|---------|----------------|--------------------------|
| `Laravel\\Ai\\Jobs\\InvokeAgent` | Queue an agent prompt-style run | direct-SDK / runtime | Use Agent Kit queued pipelines for package workflows that need budgets, memory, result handlers, provider policy, and telemetry. Use the SDK job for thin SDK-only async prompts. |
| `Laravel\\Ai\\Jobs\\BroadcastAgent` | Queue streaming output to broadcast channels | direct-SDK | Use the SDK job when the application specifically wants the SDK broadcast contract. Use `StreamingAiRuntime` for Agent Kit runtime streaming/failover/failure normalization. |
| `Laravel\\Ai\\Jobs\\GenerateEmbeddings` | Queue embedding generation | runtime | Use `EmbeddingsRuntime` inside application jobs or Agent Kit pipeline steps when you need package DTOs or vector workflow composition. |
| `Laravel\\Ai\\Jobs\\GenerateImage` | Queue image generation | runtime | Use `ImageGenerationRuntime` inside application jobs or pipeline steps. Use SDK job directly for SDK-only async image generation. |
| `Laravel\\Ai\\Jobs\\GenerateAudio` | Queue audio generation | runtime | Use `AudioGenerationRuntime` inside application jobs or pipeline steps. Use SDK job directly for SDK-only async audio generation. |
| `Laravel\\Ai\\Jobs\\GenerateTranscription` | Queue transcription | runtime | Use `TranscriptionRuntime` inside application jobs or pipeline steps. Use SDK job directly for SDK-only transcription queues. |

Shared job concern (not a standalone queue entry point):

| SDK surface | Purpose | Classification | Agent Kit recommendation |
|-------------|---------|----------------|--------------------------|
| `Laravel\\Ai\\Jobs\\Concerns\\InvokesQueuedResponseCallbacks` | Invokes serialized callbacks on queued modality/agent responses | out of scope | Internal SDK behavior used by the jobs above. Do not wrap unless a package-owned queued callback contract is proposed. |

## Agent Kit queued pipeline guidance

Prefer Agent Kit queued pipelines when the workflow needs any of these package-owned behaviors:

- package `RunContext` state and metadata
- conversation ID propagation or package memory persistence
- package retry and budget enforcement
- result handlers
- redacted package telemetry
- deterministic package fakes around workflow state

Prefer Laravel AI SDK jobs directly when:

- the job is a thin SDK call with no Agent Kit workflow state
- the application wants the SDK's queue/broadcast contract exactly
- the application needs SDK-specific provider behavior that Agent Kit has intentionally not wrapped
- the test should exercise Laravel AI SDK behavior rather than package contracts

## Release audit steps

1. Run `composer show laravel/ai` in the release validation environment and record the installed version in the relevant release/change notes.
2. Scan `vendor/laravel/ai/src/Jobs` after SDK upgrades.
3. Add new jobs to this inventory and classify them.
4. Update public docs only when the recommendation changes for application developers.
5. Open follow-up OpenSpec changes only for package-owned job gaps that need package policy, memory, telemetry, or fakes.
