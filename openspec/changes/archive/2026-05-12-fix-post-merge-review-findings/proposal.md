## Why

Two post-merge review findings need a focused corrective change before the next release.

1. Database conversation message persistence now uses an `upsert()` conflict target of `conversation_record_id` + `message_id`. New installs receive that composite unique index from the current migration stub, but applications that already ran the previous migration only have the old global `message_id` unique index. PostgreSQL and SQLite require the `upsert()` conflict target to match an existing unique/exclusion constraint, so existing installs can fail at save time after upgrade. MySQL can also keep the old global `message_id` uniqueness semantics, which conflicts with the new per-conversation message identity.

2. Streaming runtime provider accounting currently records provider success immediately after stream creation. If the stream later yields `StreamError` or throws during iteration, the terminal stream failure is reported but the provider failure is not recorded. With circuit-breaker filtering enabled, a provider that repeatedly fails mid-stream may remain closed and keep being selected.

## What Changes

- Add a defensive upgrade migration for existing `ai_agent_conversation_messages` tables:
  - add the composite unique index on `conversation_record_id` + `message_id` when missing;
  - drop the old global unique index on `message_id` when present;
  - preserve the existing `conversation_record_id` + `sequence` unique index.
- Keep `DatabaseConversationStore` message persistence aligned with the composite message identity.
- Update streaming runtime circuit-breaker accounting:
  - record provider success only after a stream completes successfully;
  - record provider failure for terminal provider stream errors and stream iteration exceptions;
  - do not record provider failure for package-local failures such as memory reconciliation errors or budget violations unless they are already classified as provider failures.
- Add regression tests for migrated/legacy schema compatibility and streaming provider health accounting.
- Update docs/changelog for the migration compatibility and streaming circuit-breaker behavior.

## Capabilities

### Modified Capabilities
- `atomic-conversation-persistence`: Existing installs can upgrade to the composite message identity required by atomic message upserts.
- `runtime-provider-failover-execution`: Streaming provider health accounting reflects the terminal stream outcome, not stream creation alone.

## Impact

- **Code areas:** database migrations, database conversation store tests, streaming runtime, streaming runtime tests, docs, changelog.
- **Public API:** No public PHP API changes expected.
- **Database:** Adds an upgrade migration for the message table index transition.
- **Operational risk:** Medium. The migration must be defensive across supported database drivers and existing index names.
