# developer-documentation-experience Specification

## Purpose
TBD - created by archiving change docs-developer-experience-cleanup. Update Purpose after archive.
## Requirements
### Requirement: Public docs are developer-facing and task-oriented

The public README and public files under `docs/` MUST prioritize application-developer tasks over package implementation history. They MUST teach the current package behavior through workflow-oriented examples, concise explanations, and links to focused guides.

Public developer docs MUST NOT require readers to understand package issue history, OpenSpec workflow, internal roadmap sequencing, or release verification process before they can install, configure, and use the package.

#### Scenario: New developer can follow the primary path

- **WHEN** a Laravel developer opens `README.md`
- **THEN** they MUST be able to find installation instructions, minimal configuration guidance, a first text workflow example, a first audio workflow example, a basic orchestration example, security defaults, and links to focused guides
- **AND** they MUST NOT be routed through maintainer-only SDK inventories, CI matrices, release verification checklists, or issue-history records as part of the primary onboarding path

### Requirement: Public docs do not contain internal implementation-history markers

Public developer-facing docs MUST NOT contain issue-history or implementation-record language such as `implementation artifact`, `OpenSpec`, roadmap issue labels like `P0-I` or `P1Y-I`, `roadmap complete`, or `archived under openspec`.

Those markers MAY exist in `CHANGELOG.md`, `CONTRIBUTING.md`, `docs/maintainers/**`, `openspec/**`, and `plan/**`.

#### Scenario: Internal markers are excluded from public docs

- **WHEN** documentation quality checks scan `README.md` and public non-maintainer files under `docs/`
- **THEN** internal implementation-history markers MUST NOT be present
- **AND** maintainer-only docs MAY keep necessary internal workflow language under `docs/maintainers/**`

### Requirement: Maintainer-only docs are separated from public onboarding

Maintainer-only documentation MUST be moved under `docs/maintainers/**` or linked from `CONTRIBUTING.md` rather than presented as primary package usage documentation.

Maintainer-only documentation includes CI matrix details, release verification, SDK capability inventory, SDK async/job inventory, contributor testing doctrine, and similar release or maintenance process material.

#### Scenario: Maintainer docs remain discoverable but not primary

- **WHEN** a maintainer opens `CONTRIBUTING.md`
- **THEN** they MUST be able to find links to maintainer docs
- **AND** a new application developer reading `README.md` MUST NOT need to read those maintainer docs to complete first usage

### Requirement: README is concise and links to focused guides

`README.md` MUST act as a developer landing page. It MUST include concise package positioning, install steps, minimal configuration, first workflow examples, a concept map, security/privacy defaults, and documentation links.

`README.md` MUST NOT attempt to serve as the complete manual for every subsystem.

#### Scenario: README links to deeper docs instead of duplicating them

- **WHEN** a reader needs details about providers, tools, memory, queues, vectors, telemetry, testing, or production readiness
- **THEN** `README.md` MUST link to the focused guide for that topic
- **AND** the deep guide MUST own the detailed explanation

### Requirement: Documentation examples reflect public package APIs

Public documentation examples MUST use package-owned public APIs and MUST avoid exposing Laravel AI SDK types as package public contracts. Examples SHOULD prefer dependency injection for application services and MAY show the `AgentKit` facade as a concise shortcut.

#### Scenario: Examples stay package-owned

- **WHEN** public docs demonstrate blueprints, orchestration, tools, memory, vectors, or runtime usage
- **THEN** examples MUST use package-owned request DTOs, result DTOs, contracts, managers, or facade shortcuts
- **AND** provider SDK objects MUST NOT be presented as package public API surfaces

### Requirement: Documentation tests protect developer experience

The repository MUST include tests or equivalent checks that protect the public documentation boundary.

The checks SHOULD verify public documentation links after moves or renames, reject internal markers from public docs, and verify key documented classes or config keys where practical.

#### Scenario: Public docs regress into internal language

- **WHEN** a public doc introduces internal issue-history markers or implementation-artifact language
- **THEN** the documentation developer-experience checks SHOULD fail with an actionable message

