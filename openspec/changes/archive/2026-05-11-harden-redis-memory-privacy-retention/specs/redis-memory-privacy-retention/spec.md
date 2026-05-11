## ADDED Requirements

### Requirement: Redis memory SHALL support encrypted payload storage
The Redis conversation store MUST support encrypting persisted conversation payloads through the package encryption service.

#### Scenario: Redis encryption enabled
- **WHEN** Redis memory encryption is enabled
- **AND** a conversation is saved
- **THEN** the stored Redis value does not expose prompt content, assistant content, metadata, or attachment references as plaintext
- **AND** the conversation can be read back by the package store

#### Scenario: Legacy plaintext payload exists
- **WHEN** Redis contains a valid plaintext conversation payload written by an earlier package version
- **THEN** the Redis conversation store can read it for compatibility

### Requirement: Redis memory encryption configuration SHALL be validated
The package configuration validator MUST reject non-boolean Redis memory encryption configuration values.

#### Scenario: Invalid Redis encryption config
- **WHEN** `memory.redis.encrypt_payloads` is configured as a non-boolean value
- **THEN** config validation fails with an explicit error

### Requirement: Redis memory SHALL use native key expiration when retention is configured
The Redis conversation store MUST set Redis-native key expiration when `retention_days` is configured.

#### Scenario: Retention configured
- **WHEN** a Redis-backed conversation is saved with retention enabled
- **THEN** the Redis key is written with a native expiration derived from the conversation retention timestamp

#### Scenario: Retention disabled
- **WHEN** a Redis-backed conversation is saved without retention
- **THEN** the Redis key is written without native expiration

### Requirement: Redis memory SHALL retain lazy expiration safety checks
Redis memory MUST continue to evaluate stored `retention_until` values on read and purge operations even when native key expiration is used.

#### Scenario: Expired payload is read before Redis removes it
- **WHEN** an expired Redis payload still exists
- **THEN** `find()` deletes it and returns null
