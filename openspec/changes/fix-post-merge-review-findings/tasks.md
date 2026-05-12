## 1. Database migration compatibility

- [x] 1.1 Add tests that recreate the legacy message table index state with global `message_id` uniqueness and no composite message identity index.
- [x] 1.2 Add an upgrade migration stub that drops the old global `message_id` unique index when present.
- [x] 1.3 Add the composite unique index on `conversation_record_id` + `message_id` when missing.
- [x] 1.4 Ensure the migration is defensive when the composite index already exists.
- [x] 1.5 Verify database conversation saves support the same `message_id` in different conversations after migration.

## 2. Streaming provider health accounting

- [ ] 2.1 Add tests proving stream creation alone does not record provider success.
- [ ] 2.2 Add tests proving terminal `StreamError` records provider failure and not provider success.
- [ ] 2.3 Add tests proving stream iteration exceptions classified as provider failures record provider failure.
- [ ] 2.4 Add tests proving successful stream completion records provider success exactly once.
- [ ] 2.5 Implement terminal-outcome provider accounting in `SdkAiRuntime::executeStream()`.
- [ ] 2.6 Ensure package-local stream completion failures do not incorrectly record provider failure.

## 3. Documentation and changelog

- [x] 3.1 Update `docs/memory.md` with upgrade migration guidance for existing message indexes.
- [x] 3.2 Update `docs/production.md` with deployment guidance for the message-index migration.
- [ ] 3.3 Update `docs/streaming-and-modalities.md` or `docs/providers.md` with streaming provider health accounting behavior.
- [ ] 3.4 Update `CHANGELOG.md` with both post-merge review fixes.

## 4. Validation

- [ ] 4.1 Run `openspec validate fix-post-merge-review-findings`.
- [ ] 4.2 Run formatting checks.
- [ ] 4.3 Run PHPStan/static analysis.
- [ ] 4.4 Run database memory migration/store test subset.
- [ ] 4.5 Run streaming runtime test subset.
- [ ] 4.6 Run full test suite if feasible.
