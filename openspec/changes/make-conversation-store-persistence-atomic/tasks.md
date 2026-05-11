## 1. Conversation row atomic save

- [x] 1.1 Add tests proving repeated saves for the same `conversation_id` are idempotent.
- [x] 1.2 Add tests proving save restores `deleted_at` to null for a previously soft-deleted conversation.
- [x] 1.3 Replace conversation read-then-insert/update with atomic write semantics keyed by `conversation_id`.
- [x] 1.4 Re-select the conversation record ID inside the transaction after atomic write.
- [x] 1.5 Preserve original `created_at` for existing rows where database semantics allow.

## 2. Message row idempotence

- [x] 2.1 Audit current message migration for uniqueness on `conversation_record_id` + `message_id`.
- [x] 2.2 Add or update migration/index strategy if needed.
- [x] 2.3 Add tests proving repeated saves do not duplicate messages.
- [x] 2.4 Add tests proving changed message sequence/content/metadata/attachments update existing rows.
- [x] 2.5 Implement atomic message upsert or duplicate-key-safe update/insert fallback.

## 3. Payload behavior preservation

- [x] 3.1 Add regression tests for encrypted database conversation payload round-trip.
- [x] 3.2 Add regression tests for attachment persistence round-trip.
- [x] 3.3 Add regression tests for retention timestamp behavior.
- [x] 3.4 Ensure metadata and attachment storage remains split as currently designed.

## 4. Concurrency regression coverage

- [x] 4.1 Add a test double or targeted integration test that simulates a duplicate-key race during save.
- [x] 4.2 Ensure the store handles or prevents the race without leaking raw database exceptions.
- [x] 4.3 Document limits: atomic persistence prevents database constraint races but does not merge concurrent semantic edits.

## 5. Documentation and changelog

- [x] 5.1 Update `docs/memory.md` with database persistence idempotence semantics.
- [x] 5.2 Update `docs/pipelines-and-queues.md` with guidance for concurrent conversation writes if needed.
- [x] 5.3 Update `CHANGELOG.md` with database memory reliability notes.

## 6. Validation

- [x] 6.1 Run `openspec validate make-conversation-store-persistence-atomic`.
- [x] 6.2 Run formatting checks.
- [x] 6.3 Run PHPStan/static analysis.
- [x] 6.4 Run database memory test subset.
- [x] 6.5 Run the full test suite if feasible.
