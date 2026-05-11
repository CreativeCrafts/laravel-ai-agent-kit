## ADDED Requirements

### Requirement: Database conversation save SHALL be atomic by conversation ID
The database conversation store MUST persist conversation rows using atomic write semantics keyed by `conversation_id`.

#### Scenario: Repeated save for same conversation
- **WHEN** the same conversation is saved multiple times
- **THEN** the store updates the existing conversation row
- **AND** does not insert duplicate conversation rows

#### Scenario: Concurrent save race
- **WHEN** two workers attempt to save the same new conversation concurrently
- **THEN** the store prevents or handles the unique-key race
- **AND** does not leak a raw database constraint exception to the caller

### Requirement: Database conversation save SHALL preserve existing storage behavior
Atomic persistence MUST preserve encryption, metadata, attachment storage, retention timestamps, soft-delete restoration, and message ordering semantics.

#### Scenario: Soft-deleted conversation is saved again
- **WHEN** a previously soft-deleted conversation is saved again
- **THEN** the conversation row is restored by clearing `deleted_at`

### Requirement: Database message persistence SHALL be idempotent
The database conversation store MUST persist messages idempotently per conversation record and message ID.

#### Scenario: Conversation with same messages is saved repeatedly
- **WHEN** a conversation with existing message IDs is saved again
- **THEN** existing message rows are updated as needed
- **AND** duplicate message rows are not inserted

#### Scenario: Message payload changes
- **WHEN** an existing message changes sequence, role, content, metadata, or attachments
- **THEN** the existing row is updated with the new stored payload

### Requirement: Atomic persistence SHALL document conflict limits
The package documentation MUST distinguish database write idempotence from semantic conflict merging.

#### Scenario: Concurrent semantic edits occur
- **WHEN** two workers save different conversation histories for the same conversation
- **THEN** the package documents the conflict behavior
- **AND** does not claim to merge divergent histories automatically
