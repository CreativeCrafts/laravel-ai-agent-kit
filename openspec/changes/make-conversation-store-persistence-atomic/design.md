## Context

`DatabaseConversationStore::save()` currently selects the conversation row by `conversation_id`, then inserts or updates. That pattern is safe in a single writer but can fail under concurrent writers because the table enforces a unique `conversation_id`. Message persistence also updates/inserts rows after loading existing messages.

## Goals

- Make conversation row save atomic for `conversation_id`.
- Make message persistence idempotent for `conversation_record_id` + `message_id`.
- Preserve current encryption, metadata, attachment, retention, soft-delete, and ordering behavior.
- Avoid public API changes.
- Add concurrency regression coverage.

## Non-Goals

- Implementing conflict-free replicated conversation merging.
- Solving semantic conflicts where two workers append different messages to the same conversation concurrently.
- Replacing the conversation store abstraction.
- Changing Redis or in-memory store behavior in this proposal.

## Design

### Conversation row persistence

Use database-native atomic write semantics keyed by `conversation_id`:

1. Build the complete conversation payload.
2. Perform `upsert()` / `updateOrInsert()` against `conversation_id`.
3. Re-select the row ID by `conversation_id` inside the transaction.
4. Use that row ID for message persistence.

The upsert/update columns should include `driver`, `store_conversation`, `is_encrypted`, `retention_until`, `last_message_at`, `summary_ciphertext`, `metadata_ciphertext`, `updated_at`, and `deleted_at`. Existing `created_at` should be preserved where practical.

### Message persistence

Prefer adding or confirming a unique index on:

```text
conversation_record_id, message_id
```

Then use atomic upsert for message rows. If migration compatibility makes the index risky, implementation may keep update/insert logic but must add retry handling for duplicate-key races.

### Deleted conversations

Saving a conversation should continue to clear `deleted_at` for that `conversation_id`, preserving the current behavior where a persisted conversation can be restored by saving it again.

### Concurrency testing

SQLite does not perfectly emulate every production database race. Tests should still cover:

- repeated saves for the same conversation are idempotent;
- saving modified metadata/messages updates existing rows;
- duplicate-key exception path is handled if a test double can simulate the race;
- migration contains the expected unique constraint for messages if added.

## Risks

- Upsert SQL semantics vary across supported databases. Mitigation: use Laravel query builder APIs and test with package-supported database matrix where feasible.
- Adding a message unique index can fail for existing duplicate rows. Mitigation: document migration impact or avoid new index if package has not yet shipped widely.
- Concurrent appends can still cause last-write-wins behavior. Mitigation: document that atomic persistence prevents database constraint races, not semantic conflict merging.

## Open Questions

- Should message rows use atomic upsert immediately or only conversation rows? Proposed: include messages if migration impact is acceptable.
- Should the package add an optimistic version column for conversation conflict detection? Proposed: not in this change; consider later if semantic conflicts become a real product requirement.
