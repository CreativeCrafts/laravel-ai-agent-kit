## ADDED Requirements

### Requirement: Capability matrix has no deferred trust gaps

`docs/laravel-ai-sdk-capability-matrix.md` MUST NOT list “Deferred follow-ups” for items covered by this change’s implementation (vector width integrity, Files/Stores observability, release trust checklist). The matrix MUST describe how provider Stores combine with `FileSearch` and optional `VectorStoreInterface` for RAG without implying unsupported automatic bridging is required for a trustworthy release.

#### Scenario: Files and Stores row reflects first-class guidance

- **WHEN** a reader opens the capability matrix
- **THEN** the Files and Stores section MUST explain the supported pattern for provider RAG using shipped services and tools
- **AND** the document MUST NOT defer Files/Stores observability to a future unnamed release once this change is implemented

### Requirement: SDK async and job inventory is maintained

The repository MUST contain a maintained document (for example `docs/sdk-async-inventory.md`) that enumerates Laravel AI SDK job classes and related async entry points relevant to agent workflows. Each entry MUST state the recommended kit integration path (`QueuedPipelineDispatcher` and `RunContext`, direct `AiRuntime` / modality runtimes, or intentional direct SDK job usage) and MUST NOT leave “escape hatch” as the only guidance without a positive recommendation.

#### Scenario: InvokeAgent job has explicit guidance

- **WHEN** the inventory lists `Laravel\Ai\Jobs\InvokeAgent` (or the current SDK namespace for the same capability)
- **THEN** the document MUST state whether apps SHOULD prefer kit pipelines for structured runs or MAY use the SDK job directly with documented trade-offs

### Requirement: Release verification checklist exists

The repository MUST include a release verification checklist (new `docs/release-verification.md` or an equivalent prominent section in `CONTRIBUTING.md`) that includes: running static analysis and tests via documented Composer scripts, validating the active OpenSpec change with `openspec validate`, re-scanning the capability matrix and inventory when `laravel/ai` is upgraded, and confirming default-deny tool authorization is documented for new installs.

#### Scenario: Maintainer can follow the checklist before tagging

- **WHEN** a maintainer prepares a release
- **THEN** they MUST be able to follow the checklist document step-by-step without reading the implementation source

### Requirement: Upgrade guide documents vector integrity and observability

`UPGRADE.md` MUST document: (1) the per-namespace embedding width contract and migration guidance for callers who previously mixed widths; (2) the existence and purpose of Files/Stores observability events and how to disable them in tests.

#### Scenario: Breaking change is called out

- **WHEN** an application previously upserted mixed-width embeddings into one namespace
- **THEN** `UPGRADE.md` MUST explain the new failure mode and the recommended remediation (re-embed, split namespaces, or normalize dimensions)
