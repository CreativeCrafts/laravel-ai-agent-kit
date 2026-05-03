## 1. Vector embedding integrity (code + tests)

- [x] 1.1 Extend or reuse `VectorOperationException` (or add `VectorEmbeddingDimensionException`) for namespace width violations; document in `UPGRADE.md` as **BREAKING** for mixed-width namespaces.
- [x] 1.2 Implement per-namespace width rules on `upsert` for `InMemoryVectorStore` (first batch establishes L; all documents in call match L; subsequent upserts match stored L).
- [x] 1.3 Implement the same rules for `DatabaseVectorStore` using transactions where needed so partial writes never occur on violation.
- [x] 1.4 Define and implement `search` behavior when a legacy row has malformed length vs query: skip row with optional debug log, or fail fast—match `specs/vector-embedding-integrity/spec.md` and document in `UPGRADE.md`.
- [x] 1.5 Implement `VectorStoreReferenceEmbedding` on `FakeVectorStore` with stable ordering consistent with `InMemoryVectorStore`; apply same `upsert` rules.
- [x] 1.6 Add Pest coverage: happy path first upsert, multi-doc same batch, reject second width, `FakeVectorStore` reference dimensions, optional legacy-row search path.
- [x] 1.7 Extend `ConfigValidator` if any new config keys are introduced; run `pint`, `phpstan`, `pest`.

## 2. Similarity search alignment

- [x] 2.1 Re-verify `SimilaritySearchTool` against new store guarantees; add/adjust tests if tool-level errors can no longer occur for built-in stores except embedding runtime failures.
- [x] 2.2 Document interaction between `tools.similarity_search.embedding_dimensions` and namespace width for custom `VectorStoreInterface` implementations in `README.md` / `UPGRADE.md`.

## 3. Files and Stores observability

- [x] 3.1 Add redacted domain event classes under `src/Observability/Events/` for Files and Stores operations (started/completed/failed as needed per design D3).
- [x] 3.2 Instrument `LaravelAiFilesService` to dispatch events (success and catchable failure paths) without leaking bodies or secrets.
- [x] 3.3 Instrument `LaravelAiStoresService` similarly.
- [x] 3.4 Add `ai-agent-kit.observability.laravel_ai_files_stores.enabled` (or equivalent) to `config/ai-agent-kit.php` with default **true**; validate in `ConfigValidator`; document disabling for tests.
- [x] 3.5 Register nothing extra in `packageBooted` unless required; prefer dispatch from services. Add Pest tests with `Event::fake()` asserting payload shape and redaction.

## 4. SDK trust surface and documentation

- [x] 4.1 Add `docs/sdk-async-inventory.md` enumerating Laravel AI jobs / async entry points with explicit kit recommendation columns; link from `README.md` and capability matrix.
- [x] 4.2 Update `docs/laravel-ai-sdk-capability-matrix.md`: remove deferred bullets superseded by this change; upgrade **Partial** rows to accurate **Covered** / **Parallel** text; link inventory and observability events.
- [x] 4.3 Add `docs/release-verification.md` with checklist (composer scripts, `openspec validate`, matrix + inventory review on `laravel/ai` bump, default-deny tools); link from `CONTRIBUTING.md` and `README.md`.
- [x] 4.4 Update `CHANGELOG.md` under `[Unreleased]` with breaking vector contract, new events, and docs.

## 5. Closure

- [x] 5.1 Run `openspec validate pre-release-trust-and-sdk-closure` after edits.
- [x] 5.2 Final `pint`, `phpstan`, `pest` on the implementation branch before merge.
