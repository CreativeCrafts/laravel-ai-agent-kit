## ADDED Requirements

### Requirement: Conversation storage SHALL align with Laravel AI conversation contracts
The package MUST provide conversation persistence abstractions compatible with Laravel AI conversation contract expectations for identity, message history, and metadata retrieval.

#### Scenario: Stored conversation can be reloaded via aligned contract
- **WHEN** a conversation is persisted by the package conversation store
- **THEN** it can be reloaded through the aligned conversation abstraction without lossy field translation

### Requirement: Conversation operations SHALL remain backward-compatible during migration
The package MUST provide a migration-compatible bridge so existing conversation records remain readable while the new aligned contract becomes the primary surface.

#### Scenario: Legacy conversation record is read after migration
- **WHEN** a conversation created before convergence is loaded
- **THEN** the package returns a valid aligned conversation object with required fields populated