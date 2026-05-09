## ADDED Requirements

### Requirement: A production checklist SHALL be available to operators

The package SHALL include a consolidated **production checklist** (in `README.md`, `UPGRADE.md`, or `docs/laravel-ai-sdk-capability-matrix.md`) covering: in-memory vs persistent drivers, vector store scalability expectations, tool authorizer requirements, encryption for database memory, and queue payload hygiene.

#### Scenario: Checklist is discoverable

- **WHEN** a maintainer follows the README link to the capability matrix or upgrade guide
- **THEN** they can find the checklist or a direct subsection without reading the entire package source.

### Requirement: Capability matrix SHALL reference deferred scaling work

The matrix SHALL continue to list **deferred** follow-ups (e.g. external vector backends, optional Stores bridge) distinct from this change’s documentation-only and guardrails scope.

#### Scenario: Deferred items remain explicit

- **WHEN** the matrix “Deferred follow-ups” section is read
- **THEN** large-scale vector retrieval beyond SQL row caps remains clearly marked as application or future work if not implemented in this change.
