## ADDED Requirements

### Requirement: AgentKit SHALL expose thin helpers for streaming and modalities

`AgentKit` / `AgentKitManager` SHALL provide documented convenience methods that resolve `StreamingAiRuntime`, `EmbeddingsRuntime`, `ImageGenerationRuntime`, `TranscriptionRuntime`, `RerankingRuntime`, and `AudioGenerationRuntime` (once shipped) from the container and delegate to them—without duplicating runtime orchestration logic.

#### Scenario: Facade methods are tested

- **WHEN** a test calls the new facade or manager methods with the application container bound
- **THEN** the resolved contract is the same singleton (or scoped binding) as direct `app()` resolution.

#### Scenario: PHPDoc stays accurate

- **WHEN** developers use IDE assistance on `AgentKit`
- **THEN** `@method` annotations list the new helpers with correct parameter and return types.

### Requirement: Documentation SHALL be updated

README SHALL briefly describe when to prefer the facade vs constructor injection; `CHANGELOG.md` SHALL mention new methods.
