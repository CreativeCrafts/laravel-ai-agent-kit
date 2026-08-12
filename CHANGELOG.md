# Changelog

All notable changes to `laravel-ai-agent-kit` will be documented in this file.

## [1.1.0] - 2026-08-12

Requires PHP 8.3+, Laravel 12 or 13, and `laravel/ai ^0.10`.

### Added

- **Provider identity resolver** — `ProviderTargetResolver` maps Agent Kit provider profiles to Laravel AI provider instances, drivers, models, and profile `provider_options` without conflating those
  identities.
- **`sdk_provider` profile key** — optional Laravel AI provider instance name. When omitted, Agent Kit uses the profile `driver`.
- **`options.provider_options`** — profile-default provider-native options applied only to the current attempt.
- **Scoped `GenerationOptions::$providerOptions`** — maps keyed by SDK provider or driver are isolated per attempt; unscoped maps remain backwards compatible.
- **`ExecutionRequest::$strictStructuredOutput`** — per-request Laravel AI strict structured output, default `false`. Forwarded by `PromptBlueprint` and `AudioImageStructuredEvaluationRequest`.
- **SDK attempt telemetry** — `runtime_sdk_provider_attempts` and `runtime_final_sdk_provider` alongside existing profile-oriented attempt metadata.
- **`runtime.default_instructions`** — opt-in package-level system instructions used only when a request supplies none.

### Changed

- Explicit profile lookup uses `ProviderRegistry`, not `failover_order`. A registered profile resolves even when it is absent from failover.
- Text and modality runtimes invoke Laravel AI with the resolved SDK provider name, not the Agent Kit profile name.
- Typed `GenerationOptions` fields (`temperature`, `maxTokens`, `maxSteps`) are exposed as Laravel AI agent methods. They are no longer mixed into `HasProviderOptions`.
- Empty instructions no longer inject `You are the Laravel AI Agent Kit runtime bridge.`
- `AudioImageStructuredEvaluation` requires `text_generation` and `structured_output`, plus `image_input` or `vision`.
- Package tests cover profile names that deliberately differ from Laravel AI provider instance names.

### Fixed

- Explicitly selected provider profiles with `enabled => false` now raise `ProviderDisabledException` instead of invoking the profile's SDK provider.
- Scoped `providerOptions` keyed by Laravel AI instance names from `config/ai.php` are isolated per attempt even when those names are not Agent Kit profiles.
- Audited capability conformance accepts `vision` as an alias for `image_input` on audio-image evaluation, matching `AudioImageStructuredEvaluation`.

### Breaking

- Requests with no instructions no longer receive a hidden Agent Kit system persona. Opt in with `runtime.default_instructions` if you relied on that text.
- Agent Kit profile names are no longer forwarded as Laravel AI provider names. If you previously created a Laravel AI provider whose name matched an Agent Kit profile as a workaround, set
  `sdk_provider` to that Laravel AI instance name.

See [UPGRADE.md](UPGRADE.md).

## v1.0.0 - 2026-07-01

First public release. Requires PHP 8.3+, Laravel 12 or 13, and `laravel/ai ^0.8`.

### Added

- **Blueprints** — `TextToStructuredEvaluation`, `AudioToTextToEvaluation`, and `AudioImageStructuredEvaluation` with typed request/result DTOs, pipeline steps, and `AgentKit` facade entry points.
- **Multi-agent orchestration** — `Agent` contract, `AgentRegistry`, `SynchronousAgentOrchestrator`, delegation policy modes (`static_only`, `dynamic_with_allowlist`, `dynamic_full_registry`), and
  trace semantics.
- **Runtime** — `SdkAiRuntime` bridge, optional runtime middleware stack, structured output via `ExecutionRequest::$schema`, streaming via `StreamingAiRuntime`, runtime budgets, provider failover with
  circuit breakers, and redacted telemetry events.
- **Memory** — `in_memory`, encrypted `database`, and encrypted `redis` conversation drivers; atomic database persistence; optional Laravel AI legacy read fallback; attachment persistence and replay
  policy.
- **Pipelines and queues** — `PipelineBuilder`, synchronous runner, `LaravelQueuedPipelineDispatcher`, and optional queued payload guards.
- **Tools** — default-deny authorizer, JSON schema validation subset, opt-in `SimilaritySearchTool`, and explicit `provider_tools` aliases for SDK-native tools.
- **Vectors and retrieval** — `VectorStoreInterface` with in-memory and database drivers, embedding dimension enforcement, and `LaravelAiFilesService` / `LaravelAiStoresService` wrappers.
- **Modalities** — transcription (including `TranscriptionAudioSource`, prompts, and diarized provider options), embeddings, image generation, reranking, and audio generation runtime contracts with
  SDK implementations.
- **Prompts** — versioned in-memory and file drivers, `PromptBlueprint`, and scaffolding via `ai:make:prompt`.
- **Testing fakes** — `FakeAiRuntime`, `FakeAgentOrchestrator`, `FakeConversationStore`, `FakeVectorStore`, `FakeTranscriptionRuntime`, and Pest assertion helpers.
- **Scaffolding commands** — `ai:make:agent`, `ai:make:pipeline`, `ai:make:tool`, and `ai:purge:conversations`.
- **Documentation** — public guides under `docs/`, maintainer references under `docs/maintainers/`, and provider profile presets in `examples/provider-profile-presets.php`.

### Changed

- **Documentation:** `README.md` is the developer landing page; topic guides cover configuration, providers, blueprints, agents, memory, pipelines, vectors, streaming, errors/telemetry, testing, and
  production.
- **Packaging:** the developer `docs/` tree ships in Composer dist archives; maintainer-only artifacts, internal reports, dev tooling config, and redirect-stub docs are excluded via `.gitattributes`
  `export-ignore`.
- `TextToStructuredEvaluation` prefers runtime `structuredOutput` with bounded text normalization fallback.
- `AiRuntime` resolves through `MiddlewareExecutingAiRuntime` when runtime middleware is configured.
- Runtime conversation bridge persists attachment payloads and supports opt-in attachment replay.
- Runtime results include provider attempt metadata and optional `estimated_cost_usd` when supplied in request metadata.

### Breaking

- `InMemoryVectorStore`, `DatabaseVectorStore`, and `FakeVectorStore` enforce a single embedding length per namespace on `upsert`. `search` skips rows whose embedding length differs from the query
  vector.

### Removed

- **`UPGRADE.md`** — first release targets new installs; operational guidance lives in `README.md` and linked docs. Reintroduce an upgrade guide when breaking changes ship to existing adopters.

### Security

- Tool execution is denied by default until tools are registered and authorized.
- Conversation storage supports encryption at rest for persistent drivers (on by default).
- Telemetry payloads are redacted by default.
- Media URL inputs are validated against SSRF: `SafeHttpUrlValidator` rejects private/reserved IPs, localhost/metadata hosts, internal host suffixes, and obfuscated IP-literal encodings (decimal, hex,
  octal, and short dotted forms), with an optional `media_input.url_allowed_hosts` allowlist.
- Media path and storage references reject null bytes, `file://`, and parent-directory traversal; `fromPath()` is documented as a trusted-administrator surface.
- Queued pipeline payload guard is enabled by default; attachment replay denies base64/local reference types and provider references by default.

### Documentation

- Maintainer SDK parity inventories target `laravel/ai ^0.8`.
- Public docs describe current package behavior without requiring readers to follow internal development sequencing.
- `docs/configuration.md` includes a complete environment variable reference mapping every `AI_AGENT_KIT_*` variable to its config key and default.
- Granular pre-1.0.0 development history is archived in [docs/maintainers/CHANGELOG-pre-1.0.0-development.md](docs/maintainers/CHANGELOG-pre-1.0.0-development.md).
