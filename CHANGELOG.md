# Changelog

All notable changes to `laravel-ai-agent-kit` will be documented in this file.

## [Unreleased]

## [1.0.0] - 2026-07-01

First public release. Requires PHP 8.3+, Laravel 12 or 13, and `laravel/ai ^0.8`.

### Added

- **Blueprints** — `TextToStructuredEvaluation`, `AudioToTextToEvaluation`, and `AudioImageStructuredEvaluation` with typed request/result DTOs, pipeline steps, and `AgentKit` facade entry points.
- **Multi-agent orchestration** — `Agent` contract, `AgentRegistry`, `SynchronousAgentOrchestrator`, delegation policy modes (`static_only`, `dynamic_with_allowlist`, `dynamic_full_registry`), and trace semantics.
- **Runtime** — `SdkAiRuntime` bridge, optional runtime middleware stack, structured output via `ExecutionRequest::$schema`, streaming via `StreamingAiRuntime`, runtime budgets, provider failover with circuit breakers, and redacted telemetry events.
- **Memory** — `in_memory`, encrypted `database`, and encrypted `redis` conversation drivers; atomic database persistence; optional Laravel AI legacy read fallback; attachment persistence and replay policy.
- **Pipelines and queues** — `PipelineBuilder`, synchronous runner, `LaravelQueuedPipelineDispatcher`, and optional queued payload guards.
- **Tools** — default-deny authorizer, JSON schema validation subset, opt-in `SimilaritySearchTool`, and explicit `provider_tools` aliases for SDK-native tools.
- **Vectors and retrieval** — `VectorStoreInterface` with in-memory and database drivers, embedding dimension enforcement, and `LaravelAiFilesService` / `LaravelAiStoresService` wrappers.
- **Modalities** — transcription (including `TranscriptionAudioSource`, prompts, and diarized provider options), embeddings, image generation, reranking, and audio generation runtime contracts with SDK implementations.
- **Prompts** — versioned in-memory and file drivers, `PromptBlueprint`, and scaffolding via `ai:make:prompt`.
- **Testing fakes** — `FakeAiRuntime`, `FakeAgentOrchestrator`, `FakeConversationStore`, `FakeVectorStore`, `FakeTranscriptionRuntime`, and Pest assertion helpers.
- **Scaffolding commands** — `ai:make:agent`, `ai:make:pipeline`, `ai:make:tool`, and `ai:purge:conversations`.
- **Documentation** — public guides under `docs/`, maintainer references under `docs/maintainers/`, and provider profile presets in `examples/provider-profile-presets.php`.

### Changed

- **Documentation:** `README.md` is the developer landing page; topic guides cover configuration, providers, blueprints, agents, memory, pipelines, vectors, streaming, errors/telemetry, testing, and production.
- **Packaging:** the developer `docs/` tree ships in Composer dist archives; maintainer-only artifacts, internal reports, dev tooling config, and redirect-stub docs are excluded via `.gitattributes` `export-ignore`.
- `TextToStructuredEvaluation` prefers runtime `structuredOutput` with bounded text normalization fallback.
- `AiRuntime` resolves through `MiddlewareExecutingAiRuntime` when runtime middleware is configured.
- Runtime conversation bridge persists attachment payloads and supports opt-in attachment replay.
- Runtime results include provider attempt metadata and optional `estimated_cost_usd` when supplied in request metadata.

### Breaking

- `InMemoryVectorStore`, `DatabaseVectorStore`, and `FakeVectorStore` enforce a single embedding length per namespace on `upsert`. `search` skips rows whose embedding length differs from the query vector.

### Removed

- **`UPGRADE.md`** — first release targets new installs; operational guidance lives in `README.md` and linked docs. Reintroduce an upgrade guide when breaking changes ship to existing adopters.

### Security

- Tool execution is denied by default until tools are registered and authorized.
- Conversation storage supports encryption at rest for persistent drivers (on by default).
- Telemetry payloads are redacted by default.
- Media URL inputs are validated against SSRF: `SafeHttpUrlValidator` rejects private/reserved IPs, localhost/metadata hosts, internal host suffixes, and obfuscated IP-literal encodings (decimal, hex, octal, and short dotted forms), with an optional `media_input.url_allowed_hosts` allowlist.
- Media path and storage references reject null bytes, `file://`, and parent-directory traversal; `fromPath()` is documented as a trusted-administrator surface.
- Queued pipeline payload guard is enabled by default; attachment replay denies base64/local reference types and provider references by default.

### Documentation

- Maintainer SDK parity inventories target `laravel/ai ^0.8`.
- Public docs describe current package behavior without requiring readers to follow internal development sequencing.
- `docs/configuration.md` includes a complete environment variable reference mapping every `AI_AGENT_KIT_*` variable to its config key and default.
- Granular pre-1.0.0 development history is archived in [docs/maintainers/CHANGELOG-pre-1.0.0-development.md](docs/maintainers/CHANGELOG-pre-1.0.0-development.md).
