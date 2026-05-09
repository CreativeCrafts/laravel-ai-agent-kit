## Context

The package already ships `VectorStoreInterface` (`in_memory`, `database`), `SimilaritySearchTool`, `LaravelAiFilesService` / `LaravelAiStoresService`, queued pipelines, and redacted runtime telemetry. The audit found: (1) **silent scoring skew** when embedding widths differ within a namespace; (2) **`FakeVectorStore`** not implementing reference-dimension behavior; (3) **no package events** for Files/Stores; (4) **capability matrix** still deferring trust-relevant items; (5) **no formal release checklist** for SDK bumps.

## Goals / Non-Goals

**Goals:**

- Make **embedding width per namespace** a hard invariant for built-in stores and the official test fake.
- Add **redacted observability** for Files/Stores operations consistent with existing event style.
- **Close documentation gaps** so “Partial” and “Deferred” are not used for audit findings at release; provide an explicit **SDK async/job inventory** and **release verification** steps.
- Preserve **backward compatibility** except where the audit identified unsafe behavior (mixed-width namespaces)—that narrow case is an intentional **BREAKING** correction.

**Non-Goals:**

- Implementing a full `VectorStoreInterface` adapter that embeds every file in a provider store automatically (provider APIs vary; bridge remains app-driven unless a minimal opt-in design is explicitly scoped in tasks).
- Replacing Laravel AI’s own queue jobs with kit implementations (documentation and inventory only).
- Distributed locking for conversation writes.

## Decisions

### D1 — Embedding width on upsert (strict namespace contract)

- **Choice A (selected):** On `upsert`, if the namespace already has at least one document, **require** `count(embedding)` to equal the existing width; if the namespace is empty, the first document sets the width. Violations throw a **typed domain exception** (e.g. extend or reuse `VectorOperationException`) with a clear message. Same rule for `FakeVectorStore`.
- **Choice B:** Only warn in logs — **rejected**; users asked for trust, not silent skew.
- **Choice C:** Validate only in `SimilaritySearchTool` — **rejected**; leaves `search()` scoring wrong for direct `VectorStoreInterface` callers.

### D2 — `FakeVectorStore` and `VectorStoreReferenceEmbedding`

- **Choice A (selected):** Implement `VectorStoreReferenceEmbedding` on `FakeVectorStore` and mirror D1 rules so Pest tests assert the same contract as production stores.
- **Choice B:** Skip fake — **rejected**; weakens confidence.

### D3 — Files/Stores observability

- **Choice A (selected):** Dispatch new package events from `LaravelAiFilesService` / `LaravelAiStoresService` after successful operations and on catchable failures, carrying **operation name**, **provider key** (if resolved), **resource identifiers** (store id, file id), **boolean success**, and **error class/message** (sanitized length cap)—**no** file bodies, **no** API keys.
- **Choice B:** Only Laravel log channel — **rejected**; inconsistent with kit’s event-first observability.

### D4 — SDK matrix and async inventory

- **Choice A (selected):** Remove deferred bullets that correspond to this change; add `docs/sdk-async-inventory.md` listing each `Laravel\Ai` job class relevant to agents with columns: purpose, kit recommendation (use `QueuedPipelineDispatcher` / `AiRuntime` / SDK job), and notes. Update matrix “Partial” rows to explain the **supported composition** (Stores + FileSearch + optional local `VectorStoreInterface`) rather than implying a missing bridge is required for trust.
- **Choice B:** Keep “Deferred” — **rejected** per user request.

### D5 — Release checklist

- **Choice A (selected):** Add `docs/release-verification.md` (or CONTRIBUTING section) with: run `openspec validate`, `composer code-check`, manual matrix row review when bumping `laravel/ai`, and confirm default-deny tool authorizer in fresh install docs.

## Risks / Trade-offs

- **[Breaking] Existing bad data** → Mitigation: document in `UPGRADE.md` under clear heading; suggest migration script pattern (re-embed or split namespaces).
- **Provider store search** still not a single `VectorStoreInterface` driver → Mitigation: document RAG as Stores + FileSearch + optional embeddings store; do not claim unsupported unified search.
- **Event volume** on hot paths → Mitigation: sync dispatch only; document; optional config `observability.laravel_ai_files_stores.enabled` default true only if negligible—prefer **always on** with minimal payload unless profiling shows need; design allows **config disable** if tasks specify it.

## Migration Plan

1. Implement code + tests behind feature branch.
2. Update `UPGRADE.md` with breaking note for mixed-width namespaces.
3. Release note in `CHANGELOG` under semver major/minor per project policy.
4. Rollback: revert commit; apps can temporarily pin prior version.

## Open Questions

- None blocking: exception class name for width mismatch SHALL align with existing `VectorOperationException` if extended rather than introducing a parallel hierarchy.
