## Context

The audit reviewed the current runtime, tools, vector store, queued pipeline, budget, and service-provider surfaces. It found strong defaults in several areas, but four focused gaps remain at important trust and reliability boundaries.

This change should be implementation-focused and low API churn. It should preserve existing public contracts where possible and add configuration only where needed.

## Goals / Non-Goals

**Goals:**

- Ensure `StreamingAiRuntime::executeStream()` reports provider/runtime failures through package-owned stream failure results even when the SDK throws before returning a stream object.
- Make package-side custom tool input validation match the nested schema capabilities already exposed to providers by `SdkToolAdapter`.
- Make `DatabaseVectorStore::upsert()` atomic for insert/update writes under concurrent indexing.
- Add a production-capable queued pipeline payload-size guard that can be enabled explicitly outside `app.debug`.
- Add regression tests for each hardening point.
- Update docs for public behavior/configuration changes.

**Non-Goals:**

- Replacing Laravel AI SDK runtime behavior.
- Adding a new queue backend or vector backend.
- Changing package-owned DTO names, contracts, or core workflow APIs.
- Removing the existing debug payload guard.
- Making tool schema support fully JSON Schema-complete beyond the currently documented package subset.

## Decisions

### D1 — Stream creation failures are normalized as provider failures

`SdkAiRuntime::executeStream()` currently handles errors thrown during stream iteration, stream error events, budget failures, and post-stream reconciliation. The `$agent->stream(...)` call itself should also be inside a failure-normalizing `try/catch`.

When stream creation throws, the runtime should:

- wrap the throwable as a `RuntimeExecutionException` with `provider_failure` category;
- dispatch `RuntimeStreamFailed` with redacted context;
- yield one `StreamFailure` terminal value;
- return without throwing the provider exception to stream consumers.

This aligns stream-creation failures with iteration failures.

### D2 — Tool validation must be recursive for supported schema shapes

`SdkToolAdapter` already maps nested `object` and `array` definitions recursively. `InMemoryToolRegistry` should validate inputs recursively for the same supported subset:

- root object schemas;
- scalar property types: `string`, `integer`, `number`, `boolean`;
- `array` item definitions when present;
- nested `object.properties`;
- nested `required` lists;
- `additionalProperties: false` at every object level;
- `nullable: true`;
- scalar `enum` values.

Unsupported schema features should remain out of scope unless already supported by `SdkToolAdapter`.

### D3 — Database vector upsert should use atomic database upsert

`DatabaseVectorStore::upsert()` should preserve the existing transaction and embedding-dimension guard, but replace `exists()` + `insert()`/`update()` with an atomic query-builder `upsert()` call keyed by `namespace` and `document_id`.

Rows should update `embedding`, `metadata`, and `updated_at` while preserving original `created_at` on existing rows.

### D4 — Queue payload guard should support production opt-in

Keep `debug_payload_guard` behavior for local development. Add an explicit guard flag that does not depend on `app.debug`, for example:

```php
'pipeline' => [
    'queued' => [
        'payload_guard' => false,
        'debug_payload_guard' => true,
        'max_serialized_job_bytes' => 524288,
    ],
],
```

The dispatcher should enforce when either:

- `payload_guard` is true, or
- `debug_payload_guard` is true and `app.debug` is true.

The exception message should reference `docs/pipelines-and-queues.md` instead of README-specific wording.

## Test Strategy

Add or update tests for:

1. `SdkAiRuntime::executeStream()` yields `StreamFailure` and dispatches `RuntimeStreamFailed` when the SDK throws during stream creation.
2. `InMemoryToolRegistry` rejects malformed nested object input.
3. `InMemoryToolRegistry` rejects malformed array item input.
4. `InMemoryToolRegistry` rejects nested additional properties when disabled.
5. `InMemoryToolRegistry` enforces nullable and enum behavior consistently with package schema support.
6. `DatabaseVectorStore::upsert()` updates existing rows through an atomic upsert path and remains idempotent for repeated same-document writes.
7. `LaravelQueuedPipelineDispatcher` enforces `payload_guard` when `app.debug` is false.
8. Existing `debug_payload_guard` behavior remains intact.

## Risks / Trade-offs

- Recursive tool validation may reject payloads that previously reached tool handlers. This is intended hardening but should be called out in changelog/docs.
- Query-builder `upsert()` SQL generation differs by database driver. Tests should run on the repository's supported database test target and avoid relying on driver-specific SQL strings.
- Production payload guard is opt-in to avoid surprising existing users.
- Stream creation failure normalization changes thrown exceptions into yielded `StreamFailure` values for `executeStream()`. This matches the streaming contract but should be documented in tests.

## Rollout Plan

1. Add tests that reproduce each finding.
2. Implement stream creation failure normalization.
3. Implement recursive tool input validation.
4. Replace database vector write loop with atomic upsert.
5. Add production queue payload guard config and dispatcher behavior.
6. Update docs and changelog.
7. Run OpenSpec validation, formatting, static analysis, and test suite.
