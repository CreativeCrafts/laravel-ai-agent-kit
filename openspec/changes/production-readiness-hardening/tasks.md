## 1. Operator documentation and checklist

- [ ] 1.1 Add a **Production checklist** subsection to `README.md` (memory/vector singletons, authorizer, encryption, queues).
- [ ] 1.2 Extend `UPGRADE.md` with queue payload hygiene for `RunContext` / `RunQueuedPipelineJob` and link from README.
- [ ] 1.3 Update `docs/laravel-ai-sdk-capability-matrix.md` to reference the checklist and reinforce O(n) vector search semantics (if not already fully explicit).

## 2. Ephemeral driver warnings (optional, design D1)

- [ ] 2.1 Add config keys under `memory` and/or `vector` (or a single `ephemeral_warnings` block) per `design.md`; default **off** or warn-disabled.
- [ ] 2.2 Implement boot-time or first-resolution log warning when enabled and environment matches (e.g. `production`); ensure **idempotent** logging (avoid log spam per request).
- [ ] 2.3 Pest test: when flag enabled + `in_memory` + env fixture, assert log or fake log expectation; when disabled, no warning.

## 3. Database vector search limits (design D2)

- [ ] 3.1 Add optional `vector.database.max_scan_rows` (name finalized in implementation) to `config/ai-agent-kit.php` with null = unlimited.
- [ ] 3.2 Implement cap in `DatabaseVectorStore::search` with **stable ordering** when cap is set (document in code comment).
- [ ] 3.3 Extend `ConfigValidator` for the new key; add `DatabaseVectorStoreTest` cases for capped vs uncapped behavior.

## 4. SimilaritySearchTool embedding alignment (design D3)

- [ ] 4.1 Implement dimension check: compare query embedding length to first available stored vector in namespace or use config `expected_dimensions` when set.
- [ ] 4.2 Return structured tool error or throw per chosen contract; document in `UPGRADE.md`.
- [ ] 4.3 Pest tests: mismatch vs match paths.

## 5. Queued pipeline payload guard (design D4)

- [ ] 5.1 Document serialization concerns in `UPGRADE.md` (explicit field list for `RunContext`).
- [ ] 5.2 Optional: add debug-only serialized-size guard in `LaravelQueuedPipelineDispatcher` or job constructor; config threshold; test with `APP_DEBUG` true in Pest.

## 6. Service provider maintainability (design D5)

- [ ] 6.1 Refactor `LaravelAiAgentKitServiceProvider::packageRegistered` into private registrar methods without changing bindings (or extract `*ServiceRegistrar` classes if cleaner).
- [ ] 6.2 Run `pint`, `phpstan`, `pest`; fix regressions.

## 7. Closure

- [ ] 7.1 Update `CHANGELOG.md` under `[Unreleased]` for all shipped behaviors and docs.
- [ ] 7.2 Validate change with `openspec validate production-readiness-hardening` when implementing.
