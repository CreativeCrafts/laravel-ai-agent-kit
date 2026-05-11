## ADDED Requirements

### Requirement: Maintainer SDK parity inventory SHALL be kept current
The project MUST maintain an SDK parity inventory for the supported Laravel AI SDK version range.

#### Scenario: SDK dependency is upgraded
- **WHEN** the supported `laravel/ai` version range changes
- **THEN** maintainers update the SDK capability matrix and async inventory
- **AND** classify new or changed SDK surfaces

### Requirement: SDK surfaces SHALL be explicitly classified
Every audited Laravel AI SDK capability MUST be classified as package-owned, direct-SDK, deferred, or out of scope.

#### Scenario: SDK capability is not wrapped by Agent Kit
- **WHEN** an SDK capability has no Agent Kit wrapper
- **THEN** maintainer documentation states whether developers should use the SDK directly, wait for a deferred wrapper, or treat it as out of scope

### Requirement: Public docs SHALL explain Agent Kit versus direct SDK usage
Developer-facing documentation MUST explain when to use Agent Kit package surfaces and when direct Laravel AI SDK usage is expected.

#### Scenario: Developer needs SDK queue job behavior
- **WHEN** an SDK queue job is better suited than Agent Kit queued pipelines
- **THEN** docs identify direct SDK usage as the recommended path

### Requirement: Package fake parity SHALL cover package-owned public surfaces
Package-owned public surfaces MUST have corresponding fake/testing guidance or an explicit documented reason for omission.

#### Scenario: Agent Kit manager exposes a modality method
- **WHEN** `AgentKitManager` exposes a package-owned modality method
- **THEN** tests or fakes provide a supported way to exercise that method without live provider calls

### Requirement: Event normalization gaps SHALL be documented
Operationally relevant Laravel AI SDK events MUST either be normalized into redacted package events or documented as direct SDK event surfaces.

#### Scenario: SDK adds a new event
- **WHEN** an SDK upgrade introduces a new event
- **THEN** maintainers classify it as package-normalized, direct-SDK, deferred, or out of scope
