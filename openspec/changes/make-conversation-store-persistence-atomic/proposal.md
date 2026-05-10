## Why

The database conversation store uses a read-then-insert/update pattern for conversation rows keyed by `conversation_id`. The conversations table has a unique `conversation_id`, so concurrent saves for the same conversation can race: two workers may both observe no existing row, then one insert succeeds and the other fails on the unique constraint.

Agent Kit now uses atomic upsert semantics for vector documents. Conversation persistence should receive the same reliability treatment because queued pipelines, orchestration, and multi-worker runtime paths can all touch conversation state.

## What Changes

- Replace read-then-insert/update conversation persistence with atomic database write semantics.
- Re-select the database record ID after the atomic conversation write so message persistence can use the internal foreign key.
- Make message persistence idempotent by using stable uniqueness for `conversation_record_id` + `message_id` where supported.
- Preserve soft-delete restoration behavior for re-saved conversations.
- Preserve encrypted payload behavior, retention timestamps, attachment persistence, and message ordering.
- Add regression tests for repeated saves and simulated concurrent save behavior.

## Capabilities

### New Capabilities
- `atomic-conversation-persistence`: Database-backed conversation save idempotence and concurrency safety.

### Modified Capabilities
- `memory`: Database memory persistence gains atomic conversation/message write guarantees.

## Impact

- **Code areas:** `src/Memory/DatabaseConversationStore.php`, conversation/message migrations, memory tests, docs, changelog.
- **Public API:** No expected public API change.
- **Migration risk:** Low to medium if a new unique index is added for messages. Existing duplicate message rows, if any, may need cleanup before adding the index.
- **Operational risk:** Lower save race risk under queued and multi-worker workloads.
