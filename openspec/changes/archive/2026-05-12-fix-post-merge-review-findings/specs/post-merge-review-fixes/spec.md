## ADDED Requirements

### Requirement: Existing database installs SHALL receive the message identity index required by atomic persistence

The package SHALL provide an upgrade migration path for applications that already ran the earlier `ai_agent_conversation_messages` migration where `message_id` was globally unique.

#### Scenario: Existing install has the old global message_id unique index

- **GIVEN** an existing `ai_agent_conversation_messages` table with a global unique index on `message_id`
- **AND** no composite unique index on `conversation_record_id` and `message_id`
- **WHEN** the upgrade migration runs
- **THEN** the old global unique index SHALL be removed when present
- **AND** a unique index on `conversation_record_id` and `message_id` SHALL exist
- **AND** the existing unique index on `conversation_record_id` and `sequence` SHALL remain valid

#### Scenario: New install already has the composite message identity index

- **GIVEN** a message table that already has the composite unique index on `conversation_record_id` and `message_id`
- **WHEN** the upgrade migration runs
- **THEN** the migration SHALL not fail because the index already exists
- **AND** message persistence SHALL continue to use the composite message identity

#### Scenario: Two conversations reuse the same message id

- **GIVEN** two database conversations with different conversation ids
- **AND** both contain a message with the same message id
- **WHEN** both conversations are saved after the upgrade migration
- **THEN** both saves SHALL succeed
- **AND** each conversation SHALL reload its own message content

### Requirement: Streaming provider health accounting SHALL reflect terminal stream outcome

The streaming runtime SHALL record provider circuit-breaker health from the terminal stream outcome, not from stream creation alone.

#### Scenario: Stream creation succeeds but stream yields a provider error

- **GIVEN** a provider stream is created successfully
- **WHEN** stream iteration yields a provider stream error
- **THEN** the runtime SHALL emit one terminal `StreamFailure`
- **AND** the provider SHALL be recorded as failed for circuit-breaker accounting
- **AND** the provider SHALL NOT be recorded as successful for that stream attempt

#### Scenario: Stream creation succeeds but stream iteration throws a provider failure

- **GIVEN** a provider stream is created successfully
- **WHEN** iterating the stream throws an exception normalized as provider failure
- **THEN** the runtime SHALL emit one terminal `StreamFailure`
- **AND** the provider SHALL be recorded as failed for circuit-breaker accounting
- **AND** the provider SHALL NOT be recorded as successful for that stream attempt

#### Scenario: Stream completes successfully

- **GIVEN** a provider stream is created successfully
- **WHEN** all chunks are consumed and completion processing succeeds
- **THEN** the runtime SHALL emit `StreamComplete`
- **AND** the provider SHALL be recorded as successful exactly once

#### Scenario: Stream completes but package-local completion work fails

- **GIVEN** provider stream iteration completes
- **WHEN** package-local completion work such as memory reconciliation fails
- **THEN** the runtime SHALL emit a terminal `StreamFailure`
- **AND** the provider SHALL NOT be recorded as failed unless the normalized failure category is `provider_failure`
