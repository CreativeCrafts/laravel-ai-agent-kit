## ADDED Requirements

### Requirement: Files service emits redacted lifecycle events

`LaravelAiFilesService` MUST dispatch package-owned domain events (or equivalent observable hooks registered with the Laravel event dispatcher) for each public operation (`put`, `putFromPath`, `putFromStorage`, `get`, `delete` as applicable to the shipped API surface). Event payloads MUST NOT include raw file contents, upload bytes, or secrets. Payloads MUST include: operation identifier, resolved provider name when available, resource identifier(s) returned by the SDK or arguments (e.g. file id), success flag, and on failure a bounded-length error summary safe for logs.

#### Scenario: Successful put is observable without content leakage

- **WHEN** a consumer completes a successful file upload through `LaravelAiFilesService`
- **THEN** the package MUST dispatch a completion event
- **AND** the event payload MUST NOT contain the uploaded file body

#### Scenario: Failed operation emits failure metadata only

- **WHEN** a file operation throws or returns a failure path documented by the service
- **THEN** the package MUST dispatch a failure-oriented event or include failure metadata on the terminal event
- **AND** the payload MUST NOT include API keys or full stack traces by default

### Requirement: Stores service emits redacted lifecycle events

`LaravelAiStoresService` MUST dispatch package-owned domain events for each public operation (`create`, `get`, `addToStore`, `removeFromStore`, `refreshStore`, `deleteStore` as applicable to the shipped API surface). The same redaction rules as the Files service MUST apply.

#### Scenario: Store creation is observable

- **WHEN** a consumer creates a provider store through `LaravelAiStoresService`
- **THEN** the package MUST dispatch an event that includes the store identifier and provider context
- **AND** the payload MUST NOT include raw file contents from referenced files

### Requirement: Observability can be disabled for tests

The package MUST provide configuration to disable Files/Stores observability event dispatch when set to false, defaulting to **enabled** in production package config so trust defaults remain on. When disabled, services MUST NOT dispatch these events.

#### Scenario: Disabled config suppresses events

- **WHEN** configuration disables Files/Stores observability
- **AND** a consumer performs a file or store operation
- **THEN** the package MUST NOT dispatch the corresponding observability events
