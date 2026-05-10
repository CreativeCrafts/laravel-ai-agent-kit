## 1. Redis encryption config

- [ ] 1.1 Add `ai-agent-kit.memory.redis.encrypt_payloads` config with secure default.
- [ ] 1.2 Validate `memory.redis.encrypt_payloads` as boolean when present.
- [ ] 1.3 Inject `EncryptionService` into `RedisConversationStore` binding.
- [ ] 1.4 Add tests for invalid config type.

## 2. Encrypted Redis payload storage

- [ ] 2.1 Add tests proving Redis save writes encrypted payloads when enabled.
- [ ] 2.2 Add tests proving encrypted Redis payloads round-trip through `find()`.
- [ ] 2.3 Add compatibility tests proving existing plaintext Redis payloads can still be read.
- [ ] 2.4 Implement encrypted wrapper format for Redis payloads.
- [ ] 2.5 Preserve existing plaintext behavior when encryption is explicitly disabled.

## 3. Redis-native retention

- [ ] 3.1 Add tests proving Redis `SET` uses `EX` when `retention_days` is configured.
- [ ] 3.2 Add tests proving Redis `SET` omits TTL when retention is null.
- [ ] 3.3 Compute TTL from `updatedAt + retentionDays` with minimum one second for past timestamps.
- [ ] 3.4 Keep lazy expiration checks on `find()` and `purgeExpired()` as a secondary guard.

## 4. Documentation and changelog

- [ ] 4.1 Update `docs/memory.md` with Redis encryption and TTL behavior.
- [ ] 4.2 Update `docs/production.md` with Redis key management and shared infrastructure guidance.
- [ ] 4.3 Update `CHANGELOG.md` with Redis memory hardening notes.

## 5. Validation

- [ ] 5.1 Run `openspec validate harden-redis-memory-privacy-retention`.
- [ ] 5.2 Run formatting checks.
- [ ] 5.3 Run PHPStan/static analysis.
- [ ] 5.4 Run Redis memory test subset.
- [ ] 5.5 Run the full test suite if feasible.
