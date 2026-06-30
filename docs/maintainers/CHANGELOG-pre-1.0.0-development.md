# Changelog (pre-1.0.0 development history)

Archived granular development notes from before the public `1.0.0` release summary. See [CHANGELOG.md](../../CHANGELOG.md) for published release notes.

---
### Added

- Transcription audio sources:
  - added `TranscriptionAudioSource` and `TranscriptionAudioSourceKind` as package-owned DTOs for base64, local path, storage, upload, and URL audio references;
  - `TranscriptionRequest::fromAudioSource(...)` lets applications use non-base64 audio without calling the underlying Laravel AI SDK directly;
  - existing `base64Audio` transcription requests remain supported;
  - `SdkTranscriptionRuntime` maps base64, path, storage, and upload sources to the matching Laravel AI SDK transcription constructors internally;
  - URL audio transcription fails closed with `UnsupportedTranscriptionAudioSourceException` until URL support is verified for the installed SDK/provider path;
  - source safe metadata records kind, MIME type, disk/reference, upload filename, or base64 payload length without logging raw media contents.
- Multimodal audio-image structured evaluation:
  - added `EvaluationImageInput` and `EvaluationImageInputKind` for URL, base64, path, storage, and upload image inputs;
  - added `AudioImageStructuredEvaluationRequest`, `AudioImageStructuredEvaluationResult`, and `AudioImageStructuredEvaluation`;
  - added `AudioImageStructuredEvaluationPipelineStep` and `AudioImageStructuredEvaluationPipeline` for synchronous or queued pipeline composition;
  - added `AgentKitManager::evaluateAudioImage(...)` and `AgentKitManager::audioImageStructuredEvaluation()` entry points;
  - audio-image evaluation transcribes audio through `TranscriptionRuntime`, then evaluates transcript plus image attachment through `AiRuntime` using caller-provided structured schemas;
  - configured providers are capability-checked for `audio_transcription`, `structured_output`, and either `image_input` or `vision`;
  - empty transcripts are rejected by default and can be allowed per request when the structured evaluator should classify malformed/empty audio.
- Testing support:
  - added `FakeTranscriptionRuntime` for source-backed transcription assertions;
  - added focused tests for source metadata, audio-image structured evaluation, capability failures, empty transcript behavior, and pipeline step state output.
- Schema-driven audio evaluation:
  - `AudioToTextToEvaluationRequest` now accepts an optional caller-provided `schema` for custom transcript evaluation output;
  - audio evaluation forwards custom schemas into the transcript evaluation runtime request;
  - `AudioToTextToEvaluationResult` now exposes raw `structuredOutput`, transcription segments, stage provider/model metadata, and usage metadata while preserving existing no-schema compatibility fields;
  - custom schema evaluation requires non-empty structured output and fails fast when the evaluation stage cannot produce it.
- Diarized transcription provider options:
  - added `TranscriptionProviderOptions` as a controlled provider-options DTO for transcription requests;
  - added `chunkingStrategy=auto` support for diarized transcription requests such as OpenAI `gpt-4o-transcribe-diarize`;
  - transcription requests now reject chunking options unless `diarize` is enabled;
  - `SdkTranscriptionRuntime` forwards controlled provider options through Laravel AI SDK transcription provider options when supported, and fails fast when the installed SDK path cannot honor them.
- Transcription prompts:
  - `TranscriptionRequest` now accepts an optional non-empty `prompt` field;
  - `SdkTranscriptionRuntime` forwards prompted transcription through Laravel AI SDK transcription provider options when supported;
  - prompted transcription fails fast when the installed SDK path cannot honor the prompt instead of silently dropping it;
  - `AudioToTextToEvaluation` transcription runtime path now passes the rendered transcription prompt into `TranscriptionRequest` while keeping prompt name/version metadata for observability.
- Post-merge review fixes:
  - added an upgrade migration for existing conversation message tables so installs using the old global `message_id` unique index can move to the required `conversation_record_id` + `message_id` unique identity;
  - streaming runtime provider health is now recorded from the terminal stream outcome: provider failures during stream creation/iteration are recorded as failures, while provider success is recorded only after successful stream completion.
- Laravel AI SDK parity governance:
  - expanded maintainer SDK capability and async inventories for the supported `laravel/ai ^0.6` range;
  - added maintainer event and provider-tool inventory with package-normalized, direct-SDK, deferred, and out-of-scope classifications;
  - public docs now clarify Agent Kit versus direct Laravel AI SDK usage, SDK jobs versus Agent Kit queued pipelines, SDK vector stores versus Agent Kit vectors, and fake/testing guidance for direct-SDK escape hatches.
- Database conversation store atomic persistence:
  - database conversation rows now use atomic write semantics keyed by `conversation_id`;
  - database message rows are persisted idempotently per conversation record and message ID;
  - saving a soft-deleted database conversation restores it by clearing `deleted_at`;
  - database memory docs now clarify that atomic persistence prevents storage-level duplicate-key races but does not merge divergent concurrent conversation histories.
- Redis memory hardening:
  - `memory.redis.encrypt_payloads` encrypts Redis conversation payloads by default using the package encryption service;
  - Redis memory can read existing plaintext Redis payloads for compatibility while writing the currently configured format;
  - Redis memory writes native Redis key TTLs when `memory.redis.retention_days` is configured and retains lazy expiration checks as a safety net.
- Runtime provider failover execution:
  - runtime requests that omit a provider now resolve the configured default provider before SDK execution;
  - prompt execution retries provider-edge failures through configured `failover_order` until success or exhaustion;
  - stream execution supports creation-only failover before any chunks are emitted;
  - runtime results include provider attempt metadata such as attempted providers, final provider, and whether failover occurred;
  - provider success/failure attempts are recorded against provider circuit breakers.
- Security, correctness, and reliability hardening from the audit pass:
  - stream creation failures are normalized into terminal `StreamFailure` values with redacted `RuntimeStreamFailed` telemetry;
  - custom tool input validation now recursively enforces the supported schema subset, including nested objects, array item schemas, `additionalProperties: false`, nullable fields, and scalar enum values;
  - `DatabaseVectorStore::upsert()` now uses atomic database upsert semantics keyed by `namespace` and `document_id`;
  - `ai-agent-kit.pipeline.queued.payload_guard` adds production-capable queued pipeline serialized payload enforcement, while `debug_payload_guard` remains debug-only.
- Developer-focused documentation structure: `docs/getting-started.md`, `docs/providers.md`, `docs/blueprints.md`, `docs/agents-and-orchestration.md`, `docs/prompts.md`, `docs/tools.md`, `docs/memory.md`, `docs/pipelines-and-queues.md`, `docs/vectors-and-retrieval.md`, `docs/streaming-and-modalities.md`, `docs/errors-and-telemetry.md`, `docs/testing.md`, and `docs/production.md`.
- Maintainer documentation namespace under `docs/maintainers/**` for CI matrix, release verification, SDK capability inventory, SDK async inventory, and contributor testing strategy.
- Documentation developer-experience test coverage for README onboarding shape, public-doc guide existence, injection-first examples, maintainer-doc separation, and public-doc internal-marker exclusions.
- `VectorEmbeddingDimensionGuard` and `VectorOperationException::forEmbeddingDimensionMismatch()` for namespace-wide embedding width enforcement.
- `VectorStoreReferenceEmbedding` on built-in vector stores for `SimilaritySearchTool` dimension checks.
- Redacted observability events `LaravelAiFilesGatewayOperationFinished` and `LaravelAiStoresGatewayOperationFinished` when using `LaravelAiFilesService` / `LaravelAiStoresService`; config `observability.laravel_ai_files_stores.enabled` (default **true**).
- `ai-agent-kit.ephemeral_driver_warnings` (default off): optional one-time-per-process log when in-memory memory or vector drivers are used in configured environments (e.g. production).
- `ai-agent-kit.vector.database.max_scan_rows`: optional cap on rows read per `DatabaseVectorStore::search` (stable `document_id` order; approximate top-K when cap &lt; namespace size).
- `ai-agent-kit.pipeline.queued.debug_payload_guard` and `max_serialized_job_bytes`: when `app.debug` is true, fail queued pipeline dispatch if serialized job exceeds threshold.
- `LaravelAiFilesService` and `LaravelAiStoresService` wrapping Laravel AI `Files` / `Stores` with package DTOs; config `laravel_ai_files.default_provider`, `laravel_ai_stores.default_provider`.
- `DatabaseVectorStore` and `ai-agent-kit.vector.default_driver` = `database`: SQL persistence for `VectorDocument` rows (`ai_agent_vector_documents` migration stub). Config: `vector.database.connection`, `vector.database.table`.
- `AudioGenerationRuntime` contract, `AudioGenerationRequest` / `AudioGenerationResult`, and `SdkAudioGenerationRuntime` (Laravel AI `Audio::of()`); config `modalities.audio_generation.default_driver`, container binding, and `ConfigValidator` support.
- `SimilaritySearchTool` (package `Tool`): embeds the query with `EmbeddingsRuntime`, searches `VectorStoreInterface`. Opt-in via `tools.similarity_search.enabled` and `tools.similarity_search.register` (defaults `false`); optional `name`, `default_namespace`, `default_limit`, and embedding overrides. Still subject to `tools.authorizer`.
- `AgentKitManager` / `AgentKit` facade: `executeStream`, `embed`, `transcribe`, `generateImage`, `rerank`, `generateAudio`, `laravelAiFiles()`, `laravelAiStores()` — thin container delegates matching `app()` bindings.

### Changed

- **Documentation:** `README.md` is now a concise developer landing page focused on install, minimal configuration, first workflow examples, core concepts, and safe defaults.
- **Documentation:** former combined public guides now redirect to focused topic guides; maintainer/process material now lives under `docs/maintainers/**` and is linked from `CONTRIBUTING.md`.
- **Packaging:** `docs/` is no longer `export-ignore` in `.gitattributes`, so the full `docs/` tree ships in Composer dist archives. README and maintainer links to `docs/*.md` resolve when the package is installed under `vendor/`.
- **BREAKING:** `InMemoryVectorStore`, `DatabaseVectorStore`, and `FakeVectorStore` enforce a **single embedding length per namespace** on `upsert` (transactional for SQL). `search` skips stored rows whose embedding length differs from the query vector (no truncated dot product).
- `FakeVectorStore` implements `VectorStoreReferenceEmbedding` and matches built-in upsert/search rules.
- `LaravelAiFilesService` and `LaravelAiStoresService` accept `Dispatcher` and emit observability events when enabled.
- `LaravelQueuedPipelineDispatcher` injects config and optional debug payload guard; `LaravelAiAgentKitServiceProvider::packageRegistered` split into private registrar methods.
- `AiRuntime` resolves to `MiddlewareExecutingAiRuntime` around `SdkAiRuntime` when `runtime.middleware` lists one or more middleware classes (blueprints and orchestration use the same binding); the same wrapper now implements `StreamingAiRuntime` and delegates streaming to the inner SDK runtime.
- `TextToStructuredEvaluation` specialist now requests structured output via `ExecutionRequest::$schema`, prefers `ExecutionResult::$structuredOutput` when valid, and falls back to the existing text normalizer when structured output is missing or invalid; coordinator forwards path flags into the final blueprint result.
- Runtime now includes `estimated_cost_usd` metadata in `ExecutionResult` when provided in request metadata.
- Governance catalog IDs for multi-agent roadmap items were aligned from `P10-I*` to `P1X-I*`, and flagship blueprint statuses were aligned to `status:ready` in roadmap/catalog metadata.
- `RuntimeConversationMemoryBridge` persists serialized attachment payloads on stored user messages; `SdkAiRuntime` merges prior-turn replay with `ExecutionRequest::$attachments` when `metadata['attachment_replay']` is `merge` or `replay_only` and `memory.attachments_replay.enabled` is true.

### Documentation

- Historical development notes remain in this changelog and archived change records. Public developer docs now describe the current package behavior without requiring readers to understand development sequencing.
- The developer docs are organized around application tasks: getting started, configuration, providers, blueprints, agents, prompts, tools, memory, queues, vectors, streaming/modalities, errors/telemetry, testing, and production readiness.

### Removed

- **`UPGRADE.md`** — First release targets new installs; migration and operational guidance now lives in **`README.md`** and linked docs. Reintroduce an upgrade guide when you ship breaking changes to existing adopters.

### Rollout: `close-agent-kit-gaps` program (Phases 0–6)

These phases shipped together in development; adopt them in dependency order (see archived change records for full proposal/design/task history):

1. **Phase 1 — Structured evaluation** — Prefer `ExecutionResult::$structuredOutput` for `TextToStructuredEvaluation`; fall back to text normalization when needed.
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
