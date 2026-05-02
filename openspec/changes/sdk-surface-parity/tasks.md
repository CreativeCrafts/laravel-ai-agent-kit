## Phase 1 — Audio generation runtime

Satisfies `specs/audio-generation-runtime/spec.md`.

- [x] 1.1 Add `Contracts/Modality/AudioGenerationRuntime.php` and request/result DTOs under `Core/Modality/` (align naming with existing modality files).
- [x] 1.2 Implement `SdkAudioGenerationRuntime` delegating to Laravel AI audio generation (`laravel/ai` `Audio` / `PendingAudioGeneration` / `AudioResponse` as appropriate).
- [x] 1.3 Register binding in `LaravelAiAgentKitServiceProvider`; add `modalities.audio_generation` to `config/ai-agent-kit.php` with `default_driver` (`sdk` | class-string).
- [x] 1.4 Extend `ConfigValidator::validateModalities()` (or equivalent) for `audio_generation`.
- [x] 1.5 Pest tests: success path with fake/test double, failure propagation, invalid config rejection.
- [x] 1.6 Update `UPGRADE.md`, `CHANGELOG.md`, and `docs/laravel-ai-sdk-capability-matrix.md` (modalities table row for `Audio::`).

## Phase 2 — Vector retrieval parity

Satisfies `specs/vector-retrieval-parity/spec.md`.

- [x] 2.1 Spike: choose second driver (`sdk_store` bridge vs database/pgvector-style) per `design.md` D4; document choice in `design.md` if updated.
- [x] 2.2 Implement second `VectorStoreInterface` driver class(es); keep `SdkBackedVectorAdapterStrategy` rules (no SDK types on the contract).
- [x] 2.3 Extend `config/ai-agent-kit.php` `vector` section with driver block(s); wire `VectorStoreInterface` resolution from `default_driver`.
- [x] 2.4 `ConfigValidator` rules for new driver keys; Pest tests for resolution and invalid driver.
- [x] 2.5 Update README vector section and capability matrix (**Partial** → **Covered** for parity story).

## Phase 3 — Laravel AI Files and Stores façade

Satisfies `specs/laravel-ai-files-stores/spec.md`.

- [x] 3.1 Define package DTOs for file references and store handles (ids, optional metadata, counts) under `Core/LaravelAi/` or `Core/ProviderStorage/` (name finalized in implementation).
- [x] 3.2 Implement `LaravelAiFilesService` (or equivalent) wrapping `Laravel\Ai\Files` for put/get/delete (and documented variants: `putFromPath`, `putFromStorage` if in scope).
- [x] 3.3 Implement `LaravelAiStoresService` (or equivalent) wrapping `Laravel\Ai\Stores` and `Laravel\Ai\Store` for create/get/add/remove/refresh/delete.
- [x] 3.4 Register singletons in service provider; add `laravel_ai_files` / `laravel_ai_stores` config section if needed for defaults (or document pure delegation to SDK config).
- [x] 3.5 Pest tests using `Files::fake()` / `Stores::fake()` (or SDK fake gateways) for deterministic store lifecycle.
- [x] 3.6 Optional: redacted domain events for store/file operations (follow existing observability patterns).
- [x] 3.7 Update `UPGRADE.md`, `CHANGELOG.md`, and capability matrix (Files / Stores rows).

## Phase 4 — SimilaritySearch-style tool

Satisfies `specs/similarity-search-tool/spec.md`.

- [x] 4.1 Decision: **ship** package tool vs **document recipe only** — record outcome in `design.md` and matrix.
- [x] 4.2 If ship: implement tool + registration path + `ToolAuthorizer` tests (deny/allow), Pest coverage per spec scenarios.
- [x] 4.3 If document-only: (skipped — shipped `SimilaritySearchTool`; Laravel AI `SimilaritySearch` remains an alternative for Eloquent/pgvector).
- [x] 4.4 Update `CHANGELOG.md`.

## Phase 5 — AgentKit facade ergonomics

Satisfies `specs/agentkit-facade-ergonomics/spec.md`.

- [x] 5.1 Add `AgentKitManager` methods: thin delegates for `StreamingAiRuntime::executeStream`, each modality runtime (including audio generation after Phase 1), following existing style.
- [x] 5.2 Extend `AgentKit` facade `@method` annotations; ensure PHPStan/Pest coverage (facade resolves same bindings as `app()`).
- [x] 5.3 README subsection: facade vs injection; update capability matrix facade row if present.
- [x] 5.4 `CHANGELOG.md` and `UPGRADE.md` note for new API surface.

## Phase 6 — Documentation and matrix closure

- [ ] 6.1 Final pass on `docs/laravel-ai-sdk-capability-matrix.md`: roadmap section marked complete or updated with deferred items explicitly listed.
- [ ] 6.2 Run `vendor/bin/pint`, `vendor/bin/phpstan`, `vendor/bin/pest`; fix regressions.
- [ ] 6.3 Archive or link this change in release notes when merging.
