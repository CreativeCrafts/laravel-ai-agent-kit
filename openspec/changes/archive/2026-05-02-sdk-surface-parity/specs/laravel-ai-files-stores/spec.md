## ADDED Requirements

### Requirement: Provider file operations SHALL be reachable through package services

The package SHALL provide a documented service (or small set of services) for Laravel AI `Files` operations: at minimum **put**, **get**, and **delete** by file id, parameterized by provider name where the SDK supports it.

#### Scenario: Put returns a package-normalized file reference

- **WHEN** an application stores a file through the package service
- **THEN** the return value exposes stable identifiers suitable for prompts, `FileSearch`, or persistence without the caller using `Laravel\Ai\Files` directly.

### Requirement: Provider vector stores SHALL be manageable through package services

The package SHALL expose create, get, add-to-store, remove-from-store, refresh, and delete-store operations aligned with `Laravel\Ai\Stores` and `Laravel\Ai\Store`, with provider selection consistent with Laravel AI configuration.

#### Scenario: Store lifecycle is integration-tested or faked deterministically

- **WHEN** tests run with Laravel AI fakes for file and store gateways
- **THEN** at least one test exercises create store → add document → get store (or equivalent) through the package API.

### Requirement: SDK types SHALL not leak through public contracts

Public methods on the new services SHALL return package DTOs or primitives; SDK response objects remain internal to adapters.

### Requirement: Documentation SHALL be updated

`UPGRADE.md`, `CHANGELOG.md`, and `docs/laravel-ai-sdk-capability-matrix.md` SHALL be updated; optional redacted observability events MAY be added following existing event naming patterns.
