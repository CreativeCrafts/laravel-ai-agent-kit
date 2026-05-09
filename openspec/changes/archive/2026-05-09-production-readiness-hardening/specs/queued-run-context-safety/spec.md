## ADDED Requirements

### Requirement: Queued pipeline payloads SHALL be documented for safety

The package SHALL document that `RunQueuedPipelineJob` serializes `RunContext` (including `input`, `state`, `metadata`, and optional `conversation`) and that operators MUST avoid **secrets** and **unbounded large payloads** in queued fields.

#### Scenario: UPGRADE or README covers queue payload rules

- **WHEN** a developer configures queued pipelines
- **THEN** documentation SHALL state payload size and sensitivity expectations.

### Requirement: Optional development guard for oversized queue payloads SHALL be configurable

The package SHALL support an opt-in development-time check (e.g. when `APP_DEBUG` is true) that warns or fails fast when the serialized job payload exceeds a configurable threshold.

#### Scenario: Production default unchanged

- **WHEN** the guard is disabled or not configured
- **THEN** dispatch behavior SHALL remain unchanged from current package behavior.
