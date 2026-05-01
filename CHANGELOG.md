# Changelog

All notable changes to `laravel-ai-agent-kit` will be documented in this file.

## [Unreleased]

### Added

- `DatabaseVectorStore` and `ai-agent-kit.vector.default_driver` = `database`: SQL persistence for `VectorDocument` rows (`ai_agent_vector_documents` migration stub). Config: `vector.database.connection`, `vector.database.table`.
- `AudioGenerationRuntime` contract, `AudioGenerationRequest` / `AudioGenerationResult`, and `SdkAudioGenerationRuntime` (Laravel AI `Audio::of()`); config `modalities.audio_generation.default_driver`, container binding, and `ConfigValidator` support.

### Documentation

- [docs/laravel-ai-sdk-capability-matrix.md](docs/laravel-ai-sdk-capability-matrix.md): maps Laravel AI SDK surfaces to Agent Kit entry points and lists roadmap gaps (audio generation, Files/Stores façade, vector driver parity).
- OpenSpec change [openspec/changes/sdk-surface-parity](openspec/changes/sdk-surface-parity/proposal.md): proposal, design, tasks, and specs for full roadmap-priority coverage (audio generation, Files/Stores, vector parity, SimilaritySearch decision, AgentKit facade).

### Rollout: `close-agent-kit-gaps` program (Phases 0–6)

These phases shipped together in development; adopt them in dependency order (see `openspec/changes/close-agent-kit-gaps/design.md`, decision D1):

1. **Phase 1 — Structured evaluation** — Prefer `ExecutionResult::$structuredOutput` for `TextToStructuredEvaluation`; fall back to text normalization when needed (`UPGRADE.md`).
2. **Phase 2 — Runtime middleware** — Register `ai-agent-kit.runtime.middleware` before relying on cross-cutting logging, tenancy, or policy around `AiRuntime::execute()`.
3. **Phase 3 — Streaming** — Inject `StreamingAiRuntime` for `executeStream()`; optional `runtime.streaming.broadcast_channel` or request metadata for Echo.
4. **Phase 4 — Modality runtimes** — Configure `ai-agent-kit.modalities.*.default_driver`; audio blueprint uses `TranscriptionRuntime` for decodable base64/data-URI audio.
5. **Phase 5 — Laravel AI legacy conversations** — When using the database memory driver, optionally enable `memory.laravel_ai_legacy` so `ConversationStore::find()` reads legacy `agent_*` rows.
6. **Phase 6 — Attachment persistence** — Run migrations (add `attachments_ciphertext` if you created tables before the stub update); enable `memory.attachments_replay` and opt in per request with `metadata['attachment_replay']` (`merge` / `replay_only` / `none`).

**Phase 0 (package hardening)** — README/composer/vector messaging and CI-aligned docs; safe to apply alongside Phase 1.

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

- Documentation: `README.md` (rollout subsection, CI links, memory options, corrected `ConversationStore` example), `UPGRADE.md` (Phase 7 index), and `CHANGELOG.md` (phased adoption order). OpenSpec change `close-agent-kit-gaps` archived as `openspec/changes/archive/2026-05-01-close-agent-kit-gaps/`; `implement-deferred-runtime-phases` proposal notes supersession.

- `AiRuntime` resolves to `MiddlewareExecutingAiRuntime` around `SdkAiRuntime` when `runtime.middleware` lists one or more middleware classes (blueprints and orchestration use the same binding); the same wrapper now implements `StreamingAiRuntime` and delegates streaming to the inner SDK runtime.
- `TextToStructuredEvaluation` specialist now requests structured output via `ExecutionRequest::$schema`, prefers `ExecutionResult::$structuredOutput` when valid, and falls back to the existing text normalizer when structured output is missing or invalid; coordinator forwards path flags into the final blueprint result.
- README documents the new observability fields and clarifies that the audio transcription stage remains plain text from the runtime.
- Runtime now includes `estimated_cost_usd` metadata in `ExecutionResult` when provided in request metadata.
- Governance catalog IDs for multi-agent roadmap items were aligned from `P10-I*` to `P1X-I*`, and flagship blueprint statuses were aligned to `status:ready` in roadmap/catalog metadata.
- `RuntimeConversationMemoryBridge` persists serialized attachment payloads on stored user messages; `SdkAiRuntime` merges prior-turn replay with `ExecutionRequest::$attachments` when `metadata['attachment_replay']` is `merge` or `replay_only` and `memory.attachments_replay.enabled` is true.
