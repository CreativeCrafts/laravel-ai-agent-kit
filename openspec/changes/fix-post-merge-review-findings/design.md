# Design: post-merge review fixes

## Scope

This change fixes two confirmed post-merge review findings:

1. Existing database installs need a migration path from global `message_id` uniqueness to conversation-scoped message identity.
2. Streaming runtime provider health accounting must reflect terminal stream outcome instead of stream creation success.

No new public runtime API is introduced.

## Database index compatibility

### Current target schema

The desired message identity is:

- `conversation_record_id`
- `message_id`

The message sequence ordering identity remains:

- `conversation_record_id`
- `sequence`

### Existing install problem

Earlier migration stubs created a global unique index on `message_id`. Existing applications that already ran that stub do not receive stub edits automatically.

`DatabaseConversationStore` now calls `upsert()` with the composite unique key. PostgreSQL and SQLite require a matching unique/exclusion constraint for that conflict target. MySQL ignores Laravel's explicit `upsert()` conflict target and relies on actual unique indexes, so keeping the old global `message_id` unique index also conflicts with the intended per-conversation identity.

### Migration strategy

Add a new migration stub that is safe to publish and run on existing installs.

The migration should:

1. Inspect existing indexes on the configured message table where possible.
2. Drop the old global unique `message_id` index if present.
3. Add the composite unique index if missing.
4. Preserve the sequence unique index.
5. Be defensive about index names where framework/schema tooling permits.

Recommended index names:

- composite message identity: `ai_agent_conversation_messages_record_message_unique`
- old global message identity likely name: `ai_agent_conversation_messages_message_id_unique`

Driver-specific fallback may be needed because schema index introspection differs across Laravel versions and database drivers.

### Data considerations

If an existing database has duplicate `message_id` values under the old global unique index, that cannot happen unless the unique index was already absent or manually changed. If the old index exists, dropping it before adding the composite index is safe for identity semantics.

If the composite index already exists, the migration must no-op for that part.

## Streaming provider accounting

### Current problem

`SdkAiRuntime::executeStream()` records provider success immediately after stream creation succeeds. The stream can still fail during iteration by yielding `StreamError` or throwing. Those terminal failure paths report stream failure but do not record provider failure.

### Desired behavior

Provider health should be recorded only from the terminal stream outcome:

- Record provider success when the stream completes successfully and package-local completion work succeeds.
- Record provider failure for provider stream errors and provider stream iteration exceptions.
- Do not record provider failure for package-local failures such as memory reconciliation errors or budget policy violations unless they are already normalized as provider failures.

### Failure classification

Use existing normalized failure categories to decide provider accounting:

- `provider_failure`: record provider failure.
- `budget_exceeded`: do not record provider failure.
- `memory`/package persistence failures: do not record provider failure.
- Unknown stream iteration throwable should be normalized through existing runtime failure normalization; record provider failure only if it normalizes to provider failure.

### Success timing

Record provider success after:

1. all stream chunks have been consumed;
2. provider/model metadata has been resolved;
3. budget checks have passed;
4. memory reconciliation has completed;
5. terminal `StreamComplete` can be emitted.

This avoids marking a provider healthy for streams that fail after creation.

## Tests

### Database

Add tests that exercise the legacy index state by creating or mutating the test schema to match the previous migration:

- old global `message_id` unique index exists;
- composite `conversation_record_id` + `message_id` index is missing.

Then run the upgrade migration and assert:

- old global index no longer blocks same `message_id` in different conversations;
- composite index exists or behavior proves it;
- `DatabaseConversationStore` can save conversations under the upgraded schema.

### Streaming

Add tests proving:

- stream creation alone does not record provider success;
- a terminal `StreamError` records provider failure;
- a stream iteration exception records provider failure when classified as provider failure;
- successful stream completion records provider success once;
- package-local post-stream failures do not record provider failure.

Use fake circuit-breaker manager or event assertions where existing package tests already expose provider health accounting.

## Documentation

Update:

- `docs/memory.md` for the upgrade migration and per-conversation message identity.
- `docs/production.md` for migration note before deploying the atomic persistence release.
- `docs/streaming-and-modalities.md` or `docs/providers.md` for terminal streaming provider health accounting.
- `CHANGELOG.md` with both fixes.
