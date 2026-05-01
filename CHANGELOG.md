# Changelog

All notable changes to `laravel-ai-agent-kit` will be documented in this file.

## [Unreleased]

### Added

- `TextToStructuredEvaluationResult` exposes `structuredEvaluationPath` and `structuredEvaluationRepaired` (and `toArray()` includes them) so callers can see whether the specialist used runtime `structuredOutput` or text normalization.
- `StructuredEvaluationOutputNormalizer::normalizeFromDecodedArray()` for validating structured payloads without round-tripping through JSON strings.
- `StructuredEvaluationJsonSchema` (`Core\Runtime`) as the stable `ObjectSchema` handle passed on specialist `ExecutionRequest` instances.
- `CreativeCrafts\LaravelAiAgentKit\Contracts\Core\RuntimeMiddleware` and `TerminatingRuntimeMiddleware`; optional ordered stack under `ai-agent-kit.runtime.middleware`.
- `MiddlewareExecutingAiRuntime` wraps the SDK runtime when middleware is configured; `ExecutionRequest::withMetadata` and `ExecutionResult::withMetadata` for middleware helpers.
- Modality runtime contracts (`TranscriptionRuntime`, `EmbeddingsRuntime`, `ImageGenerationRuntime`, `RerankingRuntime`) with request/result DTOs under `Core\Modality` and SDK-backed implementations (`Sdk*` classes).
- `ai-agent-kit.modalities` configuration for per-modality `default_driver` (`sdk` or custom class-string).
- `AudioToTextToEvaluation` transcription stage uses `TranscriptionRuntime` when `audio_reference` is decodable base64 or a data URI; falls back to the existing prompt + `AiRuntime` path otherwise.
- Runtime budget enforcement for `max_tokens`, `max_tool_calls`, and `max_cost_usd` in the SDK runtime bridge via `RuntimeBudgetEnforcer`.
- Typed runtime budget exceptions with explicit failure reasons for exceeded ceilings and invalid/missing cost metadata.
- Runtime budget regression coverage in `tests/SdkAiRuntimeTest.php`.
- `CreativeCrafts\LaravelAiAgentKit\Contracts\Core\StreamingAiRuntime` and `SdkAiRuntime::executeStream()` / `MiddlewareExecutingAiRuntime::executeStream()` for ordered text streaming (`StreamChunk`, `StreamComplete`, `StreamFailure`).
- Redacted streaming observability events `RuntimeStreamChunkEmitted`, `RuntimeStreamCompleted`, and `RuntimeStreamFailed`; optional `ShouldBroadcast` when `runtime.streaming.broadcast_channel` or request metadata `streaming_broadcast_channel` is set.
- `RequestObservabilityKeys` helper for metadata key extraction shared with streaming completion events.
- Optional Laravel AI legacy conversation read bridge: `memory.laravel_ai_legacy` config, `LegacyLaravelAiDatabaseConversationReader`, and `FallingBackToLegacyLaravelAiConversationStore` so `ConversationStore::find()` can load `agent_conversations` / `agent_conversation_messages` when the package store has no row (database driver only).
- Conversation attachment persistence: `ai_agent_conversation_messages.attachments_ciphertext` (see migration stub), Redis message `attachments` field, `AttachmentReplayPolicy`, `RuntimeAttachmentReplayResolver`, and `RuntimeAttachmentsReplayed` when policy excludes replayed attachments.

### Changed

- `AiRuntime` resolves to `MiddlewareExecutingAiRuntime` around `SdkAiRuntime` when `runtime.middleware` lists one or more middleware classes (blueprints and orchestration use the same binding); the same wrapper now implements `StreamingAiRuntime` and delegates streaming to the inner SDK runtime.
- `TextToStructuredEvaluation` specialist now requests structured output via `ExecutionRequest::$schema`, prefers `ExecutionResult::$structuredOutput` when valid, and falls back to the existing text normalizer when structured output is missing or invalid; coordinator forwards path flags into the final blueprint result.
- README documents the new observability fields and clarifies that the audio transcription stage remains plain text from the runtime.
- Runtime now includes `estimated_cost_usd` metadata in `ExecutionResult` when provided in request metadata.
- Governance catalog IDs for multi-agent roadmap items were aligned from `P10-I*` to `P1X-I*`, and flagship blueprint statuses were aligned to `status:ready` in roadmap/catalog metadata.
- `RuntimeConversationMemoryBridge` persists serialized attachment payloads on stored user messages; `SdkAiRuntime` merges prior-turn replay with `ExecutionRequest::$attachments` when `metadata['attachment_replay']` is `merge` or `replay_only` and `memory.attachments_replay.enabled` is true.

### Changed
