## Why

The database conversation store encrypts payloads by default, but the Redis memory store serializes prompt content, assistant output, metadata, and attachment references as raw JSON. The Redis store also records logical retention timestamps inside the payload without setting Redis key TTLs, so expired conversations remain in Redis unless purge logic runs.

Redis is often used as shared production infrastructure. Agent Kit should provide parity with database memory privacy guarantees and use Redis-native expiration for operational retention enforcement.

## What Changes

- Add Redis memory payload encryption support controlled by `ai-agent-kit.memory.redis.encrypt_payloads`.
- Default Redis memory encryption to true unless backward compatibility requires a staged default.
- Encrypt/decrypt the complete Redis JSON payload through the package `EncryptionService`.
- Add Redis-native TTL when `retention_days` is configured.
- Preserve lazy expiration checks as a secondary guard.
- Update docs and tests for Redis memory privacy, retention, and migration guidance.

## Capabilities

### New Capabilities
- `redis-memory-privacy-retention`: Redis conversation storage privacy and native retention behavior.

### Modified Capabilities
- `memory`: Redis memory driver behavior gains encryption and native key expiration.
- `config-validation`: Redis memory config validation covers encryption and TTL-related settings.

## Impact

- **Code areas:** `src/Memory/RedisConversationStore.php`, service provider memory binding, config file, config validator, tests, and docs.
- **Public API:** No direct API change; config surface expands.
- **Migration risk:** Medium if encryption default changes for existing Redis payloads. Implementation should support reading existing plaintext payloads during a transition or document a clear migration/flush path.
- **Operational risk:** Lower stale-data risk. Redis memory keys may expire automatically after configured retention.
