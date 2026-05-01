## ADDED Requirements

### Requirement: UPGRADE SHALL document phased externally observable changes

Each phase that changes public API, configuration keys, default behavior, or persistence format MUST append or revise `UPGRADE.md` with migration steps before the phase is considered complete.

#### Scenario: Upgrade notes exist for a breaking config key

- **WHEN** a configuration key is added, renamed, or changes accepted values
- **THEN** `UPGRADE.md` contains a dated section describing the change and migration guidance

### Requirement: README SHALL stay aligned with public entry points

README examples and configuration excerpts MUST reflect the current recommended entry points (`AgentKit`, `AgentKitManager`, contracts) and MUST not reference removed or non-existent workflow files.

#### Scenario: Installation and execution examples match the codebase

- **WHEN** a new developer follows README installation and a documented execution example
- **THEN** the referenced Artisan publish tags, config keys, and class names resolve in the installed package version

### Requirement: Release notes SHALL summarize phased rollout

The change MUST produce release notes (in `CHANGELOG.md` or release documentation) summarizing which phases shipped in which version and recommended adoption order.

#### Scenario: Changelog entry references phase identifiers

- **WHEN** a release ships a subset of this change’s phases
- **THEN** `CHANGELOG.md` (or equivalent) lists the shipped capabilities using the same phase names as `tasks.md`
