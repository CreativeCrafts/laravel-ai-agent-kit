## Context

Database memory encrypts payload columns by default. Redis memory currently stores the complete conversation payload as JSON and relies on logical `retention_until` checks plus manual purge. This leaves sensitive content readable in Redis and can leave expired records resident indefinitely if purge is not scheduled.

## Goals

- Provide Redis memory encryption parity with database memory.
- Use Redis-native expiration when retention is configured.
- Preserve compatibility for existing plaintext Redis payloads where feasible.
- Keep attachment replay behavior unchanged except for protected storage.
- Make operational behavior clear in documentation.

## Non-Goals

- Adding a new Redis schema versioning framework beyond what this change needs.
- Encrypting individual JSON fields separately.
- Replacing database memory encryption behavior.
- Supporting cross-application Redis payload sharing without shared encryption keys.

## Design

### Config

Add:

```php
'memory' => [
    'redis' => [
        'encrypt_payloads' => true,
    ],
],
```

Validation must require `bool` when present.

### Payload format

Recommended encrypted format:

```json
{
  "encrypted": true,
  "payload": "<encrypted-string>"
}
```

The encrypted string contains the existing JSON payload. This wrapper allows the store to identify encrypted payloads and optionally read legacy plaintext payloads.

### Backward compatibility

`find()` should support:

- new encrypted wrapper payloads;
- existing plaintext JSON conversation payloads;
- malformed payloads should continue to raise `ConversationStoreException`.

`save()` should always write the configured format. If `encrypt_payloads` is false, it may keep the current plaintext payload shape.

### Redis TTL

When `retentionDays` is configured, `save()` should set Redis expiration using `SET key value EX seconds` or equivalent. TTL seconds should be derived from `conversation.updatedAt + retentionDays` relative to now, with a minimum of 1 second when the computed timestamp is in the past.

When `retentionDays` is null, save should continue to write without TTL.

### Lazy retention

Keep `retention_until` in the JSON payload and retain lazy expiration checks on `find()` and `purgeExpired()`. Redis TTL becomes the primary operational cleanup mechanism; lazy checks remain a safety net.

## Risks

- Existing plaintext Redis conversations require compatibility reads. Mitigation: detect encrypted wrapper and fall back to legacy JSON decode.
- Changing encryption default can surprise applications that inspect Redis payloads manually. Mitigation: document behavior and allow explicit opt-out.
- Different apps sharing Redis keys need matching app encryption keys. Mitigation: document this requirement.

## Open Questions

- Should the default for `encrypt_payloads` be true immediately? Proposed: yes for security, because Redis memory is newly hardened and payloads are package-internal.
- Should the package offer a one-time Redis migration command? Proposed: no; Redis memory is ephemeral and compatibility reads plus write-forward behavior are enough.
