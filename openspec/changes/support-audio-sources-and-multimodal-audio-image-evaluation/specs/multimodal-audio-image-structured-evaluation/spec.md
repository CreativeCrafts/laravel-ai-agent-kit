## ADDED Requirements

### Requirement: Agent Kit SHALL provide multimodal audio-image structured evaluation

Agent Kit MUST provide a package-owned workflow that transcribes audio, evaluates the transcript together with an image, and returns structured output through Agent Kit contracts.

#### Scenario: Audio-image structured evaluation succeeds

- **WHEN** a caller submits an audio-image structured evaluation request with a supported audio source, supported image input, evaluation instructions, and a schema
- **THEN** Agent Kit transcribes the audio through `TranscriptionRuntime`
- **AND** Agent Kit evaluates the transcript plus image through `AiRuntime`
- **AND** the result includes transcript text, structured output, raw output, provider/model metadata, usage metadata, and source metadata

#### Scenario: Application does not call the underlying SDK directly

- **WHEN** a caller uses the audio-image structured evaluation workflow
- **THEN** application code interacts only with Agent Kit request/result DTOs, runtime contracts, facade methods, pipeline definitions, or blueprints
- **AND** Laravel AI SDK transcription/image/runtime objects are constructed only inside Agent Kit bridge code

### Requirement: Evaluation stage SHALL support caller-provided schemas

The multimodal evaluation workflow MUST accept the same schema forms supported by Agent Kit runtime execution.

#### Scenario: Class-string schema is used

- **WHEN** a caller passes a class-string schema implementing the Laravel AI structured-output contract supported by Agent Kit runtime
- **THEN** the workflow forwards that schema to the evaluation-stage `ExecutionRequest`
- **AND** the final result exposes the mapped structured output

#### Scenario: Object or closure schema is used

- **WHEN** a caller passes an object schema or closure schema accepted by Agent Kit runtime
- **THEN** the workflow forwards that schema to the evaluation-stage `ExecutionRequest`
- **AND** the final result exposes the mapped structured output

### Requirement: Evaluation stage SHALL support image input variants through package-owned DTOs

The workflow MUST accept package-owned image input objects and convert them internally to Laravel AI SDK image attachments.

#### Scenario: Remote image URL is evaluated

- **WHEN** a caller passes an image URL input
- **THEN** Agent Kit converts it internally to a runtime image attachment
- **AND** evaluates it with the transcript in a single structured runtime request

#### Scenario: Stored image is evaluated

- **WHEN** a caller passes a stored image input
- **THEN** Agent Kit converts it internally to a runtime image attachment using the configured disk/path
- **AND** evaluates it with the transcript in a single structured runtime request

### Requirement: Provider capabilities SHALL be validated before workflow execution when possible

The workflow MUST fail closed when selected providers are known not to support required transcription, image input, or structured output capabilities.

#### Scenario: Transcription provider lacks audio transcription capability

- **WHEN** the selected transcription provider does not advertise audio transcription support
- **THEN** the workflow throws a package-owned provider capability exception before transcription dispatch when provider metadata is available

#### Scenario: Evaluation provider lacks image or structured output capability

- **WHEN** the selected evaluation provider does not advertise image input or structured output support
- **THEN** the workflow throws a package-owned provider capability exception before evaluation dispatch when provider metadata is available

#### Scenario: Provider capability is unknown

- **WHEN** provider capability metadata is unavailable or non-authoritative
- **THEN** the workflow either attempts execution and normalizes provider failure through existing runtime error handling, or fails closed if strict capability validation is enabled
- **AND** documentation explains the selected default behavior

### Requirement: Empty transcript behavior SHALL be configurable

The workflow MUST support both conservative transcription workflows that reject empty transcripts and scoring workflows that intentionally let the structured evaluator classify empty or malformed audio.

#### Scenario: Empty transcript rejected by default

- **WHEN** transcription returns an empty transcript
- **AND** the request does not allow empty transcripts
- **THEN** the workflow fails with a package-owned empty transcript exception before evaluation dispatch

#### Scenario: Empty transcript allowed by request

- **WHEN** transcription returns an empty transcript
- **AND** the request allows empty transcripts
- **THEN** the workflow still dispatches the evaluation-stage structured request with the empty transcript

### Requirement: Workflow SHALL integrate with pipelines and queued pipelines

The multimodal audio-image structured evaluation workflow MUST be usable as a direct blueprint and as a deterministic pipeline/queued-pipeline composition.

#### Scenario: Direct blueprint execution

- **WHEN** a caller invokes the workflow directly
- **THEN** Agent Kit runs the transcription and evaluation stages synchronously and returns an evaluation result

#### Scenario: Queued pipeline execution

- **WHEN** a caller dispatches the workflow through Agent Kit queued pipeline infrastructure
- **THEN** the serialized payload contains only Agent Kit DTOs and scalar metadata
- **AND** no Laravel AI SDK objects are serialized in the queued payload

### Requirement: Workflow fakes SHALL support deterministic testing

Agent Kit MUST provide fake/test strategies for the new multimodal workflow without live provider calls.

#### Scenario: Fake transcription and evaluation produce deterministic result

- **WHEN** tests fake transcription and runtime structured output
- **THEN** the workflow returns a deterministic `AudioImageStructuredEvaluationResult`
- **AND** tests can assert the transcription request, image input, schema, provider, model, and final structured output
