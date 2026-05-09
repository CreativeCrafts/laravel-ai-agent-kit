# SDK async inventory

This maintainer reference maps Laravel AI SDK queue-oriented entry points to Agent Kit guidance. Update it when upgrading `laravel/ai`.

## Recommendation legend

| Recommendation | Meaning |
|----------------|---------|
| Pipeline | Prefer `QueuedPipelineDispatcher`, `RunQueuedPipelineJob`, and `RunContext` for structured package workflows. |
| Runtime | Prefer package runtime or modality contracts inside application-owned jobs or pipeline steps. |
| SDK job | Use the Laravel AI SDK job directly only when the app intentionally wants the SDK queue contract. |

## SDK jobs

| SDK job | Purpose | Agent Kit recommendation |
|---------|---------|--------------------------|
| `Laravel\\Ai\\Jobs\\InvokeAgent` | Queue an agent prompt-style run | Pipeline for structured Agent Kit workflows; SDK job for thin SDK-only async prompts. |
| `Laravel\\Ai\\Jobs\\BroadcastAgent` | Queue streaming output to broadcast channels | SDK job when that channel contract is required; Agent Kit streaming for package runtime streaming. |
| `Laravel\\Ai\\Jobs\\GenerateEmbeddings` | Queue embedding generation | Runtime through `EmbeddingsRuntime` inside app jobs or pipeline steps. |
| `Laravel\\Ai\\Jobs\\GenerateImage` | Queue image generation | Runtime through `ImageGenerationRuntime`. |
| `Laravel\\Ai\\Jobs\\GenerateAudio` | Queue audio generation | Runtime through `AudioGenerationRuntime`. |
| `Laravel\\Ai\\Jobs\\GenerateTranscription` | Queue transcription | Runtime through `TranscriptionRuntime`. |

## Maintenance process

1. Scan `vendor/laravel/ai/src/Jobs` after SDK upgrades.
2. Add new jobs to this inventory.
3. Update public docs only when the recommendation changes for application developers.
4. Update `CHANGELOG.md` for notable behavior or guidance changes.
