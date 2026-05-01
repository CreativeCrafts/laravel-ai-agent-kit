## ADDED Requirements

### Requirement: README SHALL reference workflows that exist in this repository

README badges and links that reference GitHub Actions workflow files MUST target workflow files present under `.github/workflows/` in this repository, OR the workflows SHALL be added to match documented names.

#### Scenario: CI badge resolves to an existing workflow file

- **WHEN** a maintainer follows the README “tests” or “code style” badge link
- **THEN** the linked workflow file exists at the referenced path in the default branch

### Requirement: Composer package description SHALL match shipped capabilities

`composer.json` description (and closely related marketing fields) MUST not claim capabilities that the package does not provide as stable public API (for example “full observability” if only redacted Laravel events are shipped).

#### Scenario: Description aligns with observability surface

- **WHEN** the package description mentions observability or telemetry
- **THEN** it describes only behaviors that are guaranteed by public contracts (for example redacted domain events) or explicitly labels preview/experimental features

### Requirement: Vector storage configuration SHALL be honest and actionable

If only the `in_memory` vector driver is implemented, configuration and README MUST state that clearly. If additional drivers are implemented, `VectorStoreInterface` binding MUST support selecting them via `ai-agent-kit.vector.default_driver` without throwing for documented driver names.

#### Scenario: Documented vector driver is bindable

- **WHEN** `ai-agent-kit.vector.default_driver` is set to a driver name documented in `config/ai-agent-kit.php` and README
- **THEN** the application container resolves `VectorStoreInterface` without error

### Requirement: Text execution spec scenarios SHALL be verifiable in CI

Tests for structured-output population MUST assert `ExecutionResult->structuredOutput` when a schema-backed call returns structured data, using a test strategy that does not rely on SDK fakes that strip `StructuredAgentResponse` (for example a focused test double or contract test).

#### Scenario: Structured output is asserted in at least one automated test

- **WHEN** CI runs the package test suite
- **THEN** at least one test exercises a schema-backed execution and expects `structuredOutput` to equal the structured payload returned by the test harness
