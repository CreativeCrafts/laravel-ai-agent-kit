# Laravel AI SDK async and jobs inventory

This document maps **Laravel AI SDK** queue-oriented and async entry points to **Laravel AI Agent Kit** guidance. It complements [laravel-ai-sdk-capability-matrix.md](laravel-ai-sdk-capability-matrix.md). When you upgrade `laravel/ai`, re-scan `vendor/laravel/ai/src/Jobs` and `vendor/laravel/ai/src` for new `ShouldQueue` types and update this file in the same change as `composer.json`.

**Legend**

| Kit recommendation | Meaning |
|--------------------|--------|
| **Pipeline** | Prefer `QueuedPipelineDispatcher` + `RunQueuedPipelineJob` + `RunContext` for structured runs, budgets, and kit observability. |
| **Runtime** | Prefer `AiRuntime` / modality runtimes (`EmbeddingsRuntime`, …) for synchronous execution inside your app or inside a pipeline step. |
| **SDK job** | Use the SDK job directly when you need Laravel AI’s serialized agent/prompt payload on the queue and do not need the kit pipeline envelope. |

---

## Queue jobs (`Laravel\Ai\Jobs\*`)

| SDK class | Purpose (summary) | Kit recommendation | Notes |
|-----------|-------------------|--------------------|--------|
| `Laravel\Ai\Jobs\InvokeAgent` | Queue an `Agent::prompt()`-style run with attachments. | **Pipeline** or **SDK job** | Use **Pipeline** when the run should participate in kit budgets, `RunContext`, and result handlers. Use **SDK job** for thin Laravel AI–only async prompts. |
| `Laravel\Ai\Jobs\BroadcastAgent` | Queue streaming agent output to broadcast channels. | **SDK job** | Kit streaming today is `StreamingAiRuntime` + optional Echo metadata on requests; use the SDK job when you specifically need `BroadcastAgent`’s channel contract. |
| `Laravel\Ai\Jobs\GenerateEmbeddings` | Queue embedding generation. | **Runtime** | Prefer `EmbeddingsRuntime::embed()` (or `AgentKit::embed()`) inside a job or pipeline step you own. |
| `Laravel\Ai\Jobs\GenerateImage` | Queue image generation. | **Runtime** | Prefer `ImageGenerationRuntime` / `AgentKit::generateImage()`. |
| `Laravel\Ai\Jobs\GenerateAudio` | Queue audio (TTS) generation. | **Runtime** | Prefer `AudioGenerationRuntime` / `AgentKit::generateAudio()`. |
| `Laravel\Ai\Jobs\GenerateTranscription` | Queue transcription. | **Runtime** | Prefer `TranscriptionRuntime` / `AgentKit::transcribe()`. |

---

## Other async / DTO entry points

| SDK surface | Purpose (summary) | Kit recommendation | Notes |
|-------------|---------------------|--------------------|--------|
| `Laravel\Ai\QueuedAgentPrompt` | DTO for queued agent prompt data (see SDK source). | **Pipeline** | Model long-running agent work as a pipeline definition + `RunContext` so memory, budgets, and observability stay consistent. |

---

## Maintenance

1. After bumping `laravel/ai`, list new files under `vendor/laravel/ai/src/Jobs/`.
2. Add a row above (or extend Notes) and update the capability matrix if user-facing guidance changes.
3. Run the steps in [release-verification.md](release-verification.md).
