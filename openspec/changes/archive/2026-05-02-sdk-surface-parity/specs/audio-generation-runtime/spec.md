## ADDED Requirements

### Requirement: Audio generation SHALL be invokable through a package modality contract

The package SHALL expose an `AudioGenerationRuntime` (or equivalent name) contract for text-to-speech / audio generation, parallel to `TranscriptionRuntime` and other modality runtimes.

#### Scenario: SDK-backed default driver

- **WHEN** `ai-agent-kit.modalities.audio_generation.default_driver` is `sdk`
- **THEN** the container resolves an implementation that delegates to Laravel AI’s audio generation path (`Laravel\Ai\Audio` / provider `AudioProvider`) without requiring application code to call the SDK facade directly.

#### Scenario: Config validation

- **WHEN** the application boots with `ai-agent-kit` validation enabled
- **THEN** `modalities.audio_generation` keys (including `default_driver`) are validated for type and allowed values per `ConfigValidator`.

### Requirement: Request and result DTOs SHALL be stable and testable

The modality SHALL use immutable request/result value objects (text input, optional voice/instructions/timeout, provider profile, model) and document mapping to SDK `PendingAudioGeneration` options.

#### Scenario: Unit tests cover success and provider failure

- **WHEN** tests run with a fake or test double for the audio gateway
- **THEN** at least one test asserts successful generation metadata and one test asserts deterministic failure propagation.

### Requirement: Documentation SHALL be updated

`UPGRADE.md`, `CHANGELOG.md`, and `docs/laravel-ai-sdk-capability-matrix.md` SHALL reflect the new modality and configuration.
