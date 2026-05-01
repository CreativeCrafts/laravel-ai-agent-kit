## ADDED Requirements

### Requirement: Attachments SHALL be persistable across conversation turns
The package MUST support persisting attachment references associated with conversation messages so subsequent turns can access prior-turn attachments according to policy.

#### Scenario: Prior-turn attachment is available on subsequent turn
- **WHEN** a user sends a follow-up prompt in a conversation that includes persisted attachments
- **THEN** the runtime receives attachment references from prior turns according to configured replay policy

### Requirement: Attachment persistence SHALL enforce lifecycle and access controls
The package MUST define attachment retention, expiration, and authorization checks before replaying persisted attachment references.

#### Scenario: Expired attachment is blocked from replay
- **WHEN** a persisted attachment exceeds retention policy or fails authorization
- **THEN** the attachment is excluded from replay and the exclusion reason is recorded for observability