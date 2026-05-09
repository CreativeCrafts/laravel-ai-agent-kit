## ADDED Requirements

### Requirement: In-memory drivers SHALL be documented as process-wide singletons

The package SHALL document that `InMemoryConversationStore` and `InMemoryVectorStore` resolve as **singletons** sharing state across all requests in the same PHP process, and are unsuitable for multi-tenant isolation or horizontal scaling without an external store.

#### Scenario: README states ephemeral semantics

- **WHEN** a developer reads the memory and vector configuration sections
- **THEN** the documentation explains singleton behavior and recommends `database` or `redis` for production multi-worker deployments.

### Requirement: Optional in-memory driver warnings SHALL be configurable

The package SHALL allow configuration that emits a **warning** (e.g. log channel) when `memory.default_driver` or `vector.default_driver` is `in_memory` while the application environment indicates production-like deployment, without throwing by default when the feature is disabled.

#### Scenario: Warning is opt-in and non-fatal

- **WHEN** the optional warning feature is disabled (default)
- **THEN** application boot SHALL NOT fail solely because `in_memory` is selected.

#### Scenario: Warning when enabled in production-like env

- **WHEN** the optional warning is enabled and `APP_ENV` is `production` (or a configured match) and an in-memory driver is active
- **THEN** the package SHALL emit at least one observable warning (e.g. log) per the design.
