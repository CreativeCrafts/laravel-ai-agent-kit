## 1. Redis encryption config

- [x] 1.1 Add `ai-agent-kit.memory.redis.encrypt_payloads` config with secure default.
- [x] 1.2 Validate `memory.redis.encrypt_payloads` as boolean when present.
- [x] 1.3 Inject `EncryptionService` into `RedisConversationStore` binding.
- [x] 1.4 Add tests for invalid config type.

## 2. Encrypted Redis payload storage

- [x] 2.1 Add tests proving Redis save writes encrypted payloads when enabled.
- [x] 2.2 Add tests proving encrypted Redis payloads round-trip through `find()`.
- [x] 2.3 Add compatibility tests proving existing plaintext Redis payloads can still be read.
- [x] 2.4 Implement encrypted wrapper format for Redis payloads.
- [x] 2.5 Preserve existing plaintext behavior when encryption is explicitly disabled.

## 3. Redis-native retention

- [x] 3.1 Add tests proving Redis `SET` uses `EX` when `retention_days` is configured.
- [x] 3.2 Add tests proving Redis `SET` omits TTL when retention is null.
- [x] 3.3 Compute TTL from `updatedAt + retentionDays` with minimum one second for past timestamps.
- [x] 3.4 Keep lazy expiration checks on `find()` and `purgeExpired()` as a secondary guard.

## 4. Documentation and changelog

- [x] 4.1 Update `docs/memory.md` with Redis encryption and TTL behavior.
- [x] 4.2 Update `docs/production.md` with Redis key management and shared infrastructure guidance.
- [x] 4.3 Update `CHANGELOG.md` with Redis memory hardening notes.

## 5. Validation

- [x] 5.1 Run `openspec validate harden-redis-memory-privacy-retention`.
- [x] 5.2 Run formatting checks.
- [x] 5.3 Run PHPStan/static analysis.
- [x] 5.4 Run Redis memory test subset.
- [x] 5.5 Run the full test suite if feasible.
