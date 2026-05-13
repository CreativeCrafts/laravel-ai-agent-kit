## ADDED Requirements

### Requirement: Audio evaluation requests SHALL support caller-defined structured schemas

`AudioToTextToEvaluation` SHALL allow callers to define the structured output schema used for the transcript evaluation stage.

#### Scenario: Caller provides a class-string schema

- **GIVEN** an audio-to-text evaluation request with a class-string schema implementing the supported structured output contract
- **WHEN** the blueprint transcribes the audio and evaluates the transcript
- **THEN** the evaluation runtime SHALL receive that schema
- **AND** the result SHALL expose structured output matching the schema
- **AND** the result SHALL include the transcript used for evaluation

#### Scenario: Caller provides an ObjectSchema

- **GIVEN** an audio-to-text evaluation request with an `ObjectSchema`
- **WHEN** the blueprint evaluates the transcript
- **THEN** the evaluation runtime SHALL receive the provided schema
- **AND** the result SHALL expose the returned structured output

#### Scenario: Caller provides a schema closure

- **GIVEN** an audio-to-text evaluation request with a schema closure
- **WHEN** the blueprint evaluates the transcript
- **THEN** the evaluation runtime SHALL receive the closure schema
- **AND** the result SHALL expose the returned structured output

### Requirement: Audio evaluation results SHALL expose transcription and evaluation data

`AudioToTextToEvaluationResult` SHALL expose the transcript, optional transcription segments, structured output, provider/model metadata, usage metadata, and compatibility data needed by existing callers.

#### Scenario: Transcription returns diarized segments

- **GIVEN** a transcription runtime result with speaker segments
- **WHEN** the audio evaluation completes
- **THEN** the final result SHALL include those segments
- **AND** the structured output SHALL remain separate from transcription segment metadata

#### Scenario: Evaluation returns structured output

- **GIVEN** the evaluation runtime returns structured output
- **WHEN** the audio evaluation completes
- **THEN** the result SHALL expose the structured output as an array
- **AND** `toArray()` SHALL include the structured output without forcing a package-defined evaluation shape

### Requirement: Existing no-schema audio evaluation behavior SHALL remain compatible

Existing callers that do not provide a custom schema SHALL continue to receive the current default evaluation behavior or a documented compatibility preset.

#### Scenario: Caller omits schema

- **GIVEN** an existing audio-to-text evaluation request without a schema
- **WHEN** the blueprint executes
- **THEN** the blueprint SHALL preserve the existing default evaluation behavior
- **AND** existing documented result fields SHALL remain available where practical

### Requirement: Schema-driven audio evaluation failures SHALL identify the failing stage

The blueprint SHALL distinguish transcription-stage failures from evaluation/schema-stage failures.

#### Scenario: Schema resolution fails

- **GIVEN** an audio-to-text evaluation request with an invalid schema
- **WHEN** the evaluation stage attempts to build the structured runtime request
- **THEN** the blueprint SHALL fail with a typed blueprint exception or runtime exception wrapper
- **AND** the original schema-resolution failure SHALL remain available as the previous exception where possible
- **AND** the error message or metadata SHALL identify the evaluation stage

#### Scenario: Transcription fails

- **GIVEN** the transcription stage fails before evaluation
- **WHEN** the blueprint handles the failure
- **THEN** the failure SHALL identify the transcription stage
- **AND** the evaluation stage SHALL not run
