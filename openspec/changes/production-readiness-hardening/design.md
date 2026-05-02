## Context

The package review identified risks that are **mostly operational**: singleton in-memory stores shared across requests, **O(n)** SQL vector search, **embedding dimension** mismatches for `SimilaritySearchTool`, **large/sensitive** `RunContext` payloads on queues, and a **large** `LaravelAiAgentKitServiceProvider`. The codebase is logically sound for its intended dev/small-scale use; this change adds **documentation, optional guards, and bounded validations** without replacing external vector databases or adding distributed locks.

## Goals / Non-Goals

**Goals:**

- Make production **foot-guns visible** in docs and optionally at runtime (opt-in warnings/limits).
- Add **deterministic, testable** checks where cheap (e.g. embedding length vs stored vector length).
- Keep **backward-compatible defaults** (no behavior change unless config enables stricter mode).
- Optionally **split service provider registration** for maintainability without changing bindings.

**Non-Goals:**

- Shipping a Pinecone/Weaviate driver (remain custom `VectorStoreInterface` bindings).
- Automatic distributed locking for `ConversationStore`.
- Changing `DenyAllToolAuthorizer` or default encryption.

## Decisions

### D1 — In-memory / ephemeral warnings

- **Choice A (preferred):** Add optional `memory.warn_in_non_local` and `vector.warn_in_non_local` (or single `ephemeral_driver_warnings.enabled`) that, when `APP_ENV` is `production` (or when `warn_when_environment` matches), **log** a single structured warning per boot if `in_memory` driver is selected — not throwing, to avoid breaking existing demos.
- **Choice B:** Throw on boot if `in_memory` in production — **rejected** as too breaking.

### D2 — Database vector search limits

- **Choice A:** Config `vector.database.max_scan_documents` (nullable = unlimited): if set, `DatabaseVectorStore::search` applies `limit()` on the query **before** in-process scoring, with **documented semantics** that results are approximate (top-K over first N rows by arbitrary order unless `orderBy` is defined — design must pick: e.g. **no order guarantee** unless we add `orderBy id` for stability).
- **Choice B:** Only document O(n) — **minimum** if scope is doc-only for v1.
- **Recommendation:** Phase 1 = docs + optional hard cap with **stable ordering** (`orderBy document_id`) when cap is set; Phase 2 consider namespace row counts.

### D3 — SimilaritySearchTool embedding alignment

- **Choice A:** After embedding the query, compare `count($vector)` to `count($firstStoredVector)` in namespace (sample one document) or to `tools.similarity_search.expected_dimensions` if set — **fail** `execute()` with `RuntimeException` or return structured tool error `{ "error": "embedding_dimension_mismatch", ... }` per existing tool JSON contract.
- **Choice B:** Require `tools.similarity_search.embedding_dimensions` when using `database` vector driver — **rejected** as too rigid; prefer runtime comparison when store has documents.

### D4 — RunContext queue safety

- **Choice A:** Document max recommended payload size; add **dev-only** assertion in `LaravelQueuedPipelineDispatcher` or `RunQueuedPipelineJob` when `APP_DEBUG` and serialized size > threshold.
- **Choice B:** Add `RunContext::estimateSerializedSize()` helper — optional if trivial.

### D5 — Service provider split

- Extract `registerMemoryBindings()`, `registerRuntimeBindings()`, etc., as **private methods** on the same class first; extract classes only if line count still excessive.

## Risks / Trade-offs

- **Capping vector DB reads** → May miss globally best matches if cap < namespace size; **Mitigation:** document trade-off; default remains unlimited.
- **Dimension check** → Extra read or config; **Mitigation:** optional fast path when `expected_dimensions` config set.
- **Logging warnings** → Noise if misconfigured env; **Mitigation:** log once per process or use `once()` pattern.

## Migration Plan

1. Ship documentation + optional config (defaults off or no-op).
2. Enable stricter checks in internal apps first.
3. Rollback: disable new config keys.

## Open Questions

- Whether `max_scan_documents` should use **random sample** vs **ordered scan** (product decision).
- Whether embedding mismatch should **throw** in `SimilaritySearchTool` or return empty results with metadata (spec will pick one).
