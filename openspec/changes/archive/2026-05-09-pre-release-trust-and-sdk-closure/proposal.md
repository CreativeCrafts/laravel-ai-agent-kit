## Why

**Status (2026-04-29):** Implementation complete on branch `cursor/production-readiness-impl-89a7`.

A pre-release audit identified gaps between **stated guarantees** and **runtime behavior** (notably vector embedding width consistency and test fidelity), gaps between **Laravel AI SDK** surfaces and **documented Agent Kit** coverage (matrix still marks items Partial or deferred), and missing **first-party observability** for Files/Stores operations. Shipping without closing these forces adopters to infer behavior from implementation details, which erodes trust. This change closes every audit finding **before** the next release so consumers can rely on explicit requirements, tests, and documentation—not deferred follow-ups.

## What Changes

- **Vector integrity:** `VectorStoreInterface` implementations that persist embeddings SHALL reject `upsert` when a document’s embedding width disagrees with the first document already present in the same namespace (or with configured expected width when the store supports it). `DatabaseVectorStore` and `InMemoryVectorStore` SHALL enforce this; `SimilaritySearchTool` behavior remains aligned. **BREAKING** for callers who currently mix widths in one namespace (unsafe data).
- **Test fidelity:** `FakeVectorStore` SHALL implement `VectorStoreReferenceEmbedding` (and the same width rules as production fakes) so tests and package guarantees stay aligned.
- **Files/Stores observability:** The package SHALL emit **redacted** domain events (or equivalent observable hooks) for successful/failed operations performed through `LaravelAiFilesService` and `LaravelAiStoresService`, mirroring the telemetry philosophy used for runtime events (no raw file contents or secrets in payloads).
- **SDK surface closure:** `docs/laravel-ai-sdk-capability-matrix.md` SHALL be updated so there are **no “Deferred follow-ups”** sections for audit items: provider RAG SHALL be described as a **first-class pattern** (Stores lifecycle + `FileSearch` provider tool + optional app `VectorStoreInterface`), and SDK queue jobs SHALL be covered by a maintained **inventory** mapping each job to kit guidance (pipeline vs direct SDK use) without leaving “escape hatch” as the only story.
- **Release trust artifacts:** `CONTRIBUTING.md` or `docs/` SHALL include a **release verification checklist** (matrix scan on `laravel/ai` bump, config validation, security defaults smoke path). `UPGRADE.md` SHALL document the vector upsert contract and observability events.

## Capabilities

### New Capabilities

- `vector-embedding-integrity`: Normative rules and tests for consistent embedding dimensions per namespace across upsert, search scoring, and similarity search tooling.
- `files-stores-observability`: Redacted events (or hooks) for Laravel AI Files/Stores service operations.
- `sdk-trust-surface-closure`: Matrix + inventory documentation + release checklist so SDK alignment and async/job stories are explicit and testable from a maintainer perspective.

### Modified Capabilities

- None (no existing `openspec/specs/` baseline in-repo for these topics; all requirements live under this change’s delta specs.)

## Impact

- **Code:** `DatabaseVectorStore`, `InMemoryVectorStore`, `FakeVectorStore`, `SimilaritySearchTool` (if any shared helpers), `LaravelAiFilesService`, `LaravelAiStoresService`, `LaravelAiAgentKitServiceProvider` (event registration if needed), new `Observability/Events/*` classes, `ConfigValidator` if new config toggles exist.
- **Docs:** `docs/laravel-ai-sdk-capability-matrix.md`, new `docs/sdk-async-inventory.md` (or equivalent), `README.md`, `UPGRADE.md`, `CHANGELOG.md`, `CONTRIBUTING.md`.
- **Consumers:** Apps that relied on mixed-dimension vectors in a single namespace must normalize embeddings or use separate namespaces—**breaking** but correctness-preserving.
