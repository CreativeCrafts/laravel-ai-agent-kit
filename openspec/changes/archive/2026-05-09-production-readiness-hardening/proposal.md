## Why

The deep code review of `laravel-ai-agent-kit` identified **operational and scalability risks** that are not primarily logic bugs: process-wide **singleton in-memory** stores, **full-table vector search** in SQL, **embedding dimension alignment** for similarity search, **large queue payloads** for `RunContext`, and **maintainability** of a large service provider. These risks are safe for development and small deployments but need **explicit contracts, optional guards, and operator documentation** so production adopters avoid foot-guns.

## What Changes

- **Documentation (first-class):** Strengthen `README.md`, `UPGRADE.md`, and `docs/laravel-ai-sdk-capability-matrix.md` with a **production checklist**: in-memory semantics, vector driver limits, queue payload hygiene, multi-worker/concurrency expectations for conversation stores.
- **Ephemeral / in-memory semantics:** Document that `InMemoryConversationStore` and `InMemoryVectorStore` are **shared process singletons**; add optional **dev/test warnings** or config flag to log when `memory.default_driver` / `vector.default_driver` is `in_memory` in non-local environments (opt-in, no breaking default).
- **Vector search scalability:** Document **O(n) per namespace** behavior for `DatabaseVectorStore::search`; add optional **configurable safeguards** (e.g. max rows scanned per search, or max namespace document count warning threshold) with fail-closed or logged behavior per design.
- **Similarity search:** Validate **query embedding dimension** matches stored vectors (or config expectation) before search; **fail fast** with a clear exception or structured tool error (per design).
- **Queued pipelines:** Document **payload size and secrets** rules for `RunContext`; optional **serialization size check** or redaction helper for queue jobs in development/testing.
- **Architecture (non-breaking):** Refactor `LaravelAiAgentKitServiceProvider` **registration** into focused private methods or small registrar classes **without** changing public bindings (optional phase if scope allows).

## Capabilities

### New Capabilities

- `ephemeral-store-semantics` — Requirements for documenting and optionally detecting unsafe in-memory driver usage in production-like environments.
- `vector-search-scalability` — Requirements for documenting DatabaseVectorStore complexity and optional operational limits or warnings.
- `similarity-search-embedding-alignment` — Requirements for embedding dimension consistency between ingestion and `SimilaritySearchTool` queries.
- `queued-run-context-safety` — Requirements for operator documentation and optional development-time guards for large or sensitive `RunContext` queue payloads.
- `operator-runbook-documentation` — Requirements for consolidated production guidance in README / UPGRADE / capability matrix.

### Modified Capabilities

- None (no `openspec/specs/` canonical tree in-repo; all behavior is additive or documentation).

## Impact

- **Public API:** Additive only unless an optional guard introduces new thrown exception types (document in `UPGRADE.md`).
- **Config:** New optional keys under `memory`, `vector`, `tools.similarity_search`, and/or `runtime` / `pipelines` per `design.md`.
- **Dependencies:** None beyond existing `laravel/ai` and Illuminate.
- **Breaking risk:** **Low** — defaults preserve current behavior; new validations are opt-in or fail-fast only where explicitly enabled.

## Non-Goals

- Replacing `DatabaseVectorStore` with an external vector database (remain an application integration concern).
- Distributed locking for conversation rows (application-level concern; may be mentioned in docs only).
- Changing default `DenyAllToolAuthorizer` or encryption defaults.
