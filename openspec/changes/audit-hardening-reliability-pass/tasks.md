## 1. Stream failure normalization

- [ ] 1.1 Add a regression test where `SdkAiRuntime::executeStream()` encounters an exception from `$agent->stream(...)` before iteration begins.
- [ ] 1.2 Ensure the streaming runtime yields a terminal `StreamFailure` instead of leaking the raw provider exception.
- [ ] 1.3 Ensure `RuntimeStreamFailed` is dispatched with redacted context for stream-creation failures.
- [ ] 1.4 Preserve existing behavior for iterator-thrown stream failures, provider `StreamError` events, budget failures, and stream completion.

## 2. Recursive custom tool input validation

- [ ] 2.1 Add tests for nested object validation, including missing nested required fields.
- [ ] 2.2 Add tests for nested `additionalProperties: false` violations.
- [ ] 2.3 Add tests for array item type validation when `items` is declared.
- [ ] 2.4 Add tests for nullable fields.
- [ ] 2.5 Add tests for scalar `enum` validation.
- [ ] 2.6 Implement recursive input validation in `InMemoryToolRegistry` for the supported package schema subset.
- [ ] 2.7 Ensure validation errors include useful property paths such as `customer.email` or `items[0]`.
- [ ] 2.8 Preserve default-deny authorization ordering and behavior.

## 3. Atomic database vector upsert

- [ ] 3.1 Add tests proving repeated upserts for the same namespace/document replace embedding and metadata idempotently.
- [ ] 3.2 Replace `exists()` plus `insert()`/`update()` with query-builder `upsert()` keyed by `namespace` and `document_id`.
- [ ] 3.3 Preserve the existing transaction and namespace embedding-dimension guard.
- [ ] 3.4 Preserve original `created_at` on existing rows and update `updated_at`.
- [ ] 3.5 Ensure empty upsert batches remain no-ops if that behavior is currently expected.

## 4. Production-capable queued pipeline payload guard

- [ ] 4.1 Add config key `ai-agent-kit.pipeline.queued.payload_guard` with default `false`.
- [ ] 4.2 Add tests showing payload guard runs when `payload_guard` is true and `app.debug` is false.
- [ ] 4.3 Add tests showing existing `debug_payload_guard` still runs only when debug is enabled.
- [ ] 4.4 Update `LaravelQueuedPipelineDispatcher` to enforce when `payload_guard` is true or when `debug_payload_guard` is true and `app.debug` is true.
- [ ] 4.5 Update the oversized payload exception message to reference `docs/pipelines-and-queues.md`.

## 5. Documentation and changelog

- [ ] 5.1 Update `docs/tools.md` to document recursive validation for the supported schema subset.
- [ ] 5.2 Update `docs/pipelines-and-queues.md` and `docs/production.md` for the new production queue payload guard.
- [ ] 5.3 Update `docs/streaming-and-modalities.md` for stream failure normalization behavior if needed.
- [ ] 5.4 Update `docs/vectors-and-retrieval.md` if vector upsert semantics need public clarification.
- [ ] 5.5 Update `CHANGELOG.md` with security/correctness/reliability hardening notes.

## 6. Validation

- [ ] 6.1 Run `openspec validate audit-hardening-reliability-pass`.
- [ ] 6.2 Run formatting checks.
- [ ] 6.3 Run PHPStan/static analysis.
- [ ] 6.4 Run the relevant Pest test subset.
- [ ] 6.5 Run the full test suite if feasible.
