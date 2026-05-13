## ADDED Requirements

### Requirement: Transcription requests SHALL accept package-owned audio sources

Agent Kit transcription MUST accept audio through a package-owned audio source abstraction rather than requiring application code to pass only base64 strings or call the underlying Laravel AI SDK directly.

#### Scenario: Base64 source remains supported

- **WHEN** a caller creates a transcription request using a base64 audio source
- **THEN** the transcription runtime maps it internally to the Laravel AI SDK base64 transcription path
- **AND** the caller does not import or call `Laravel\Ai\Transcription` directly

#### Scenario: Local path source is supported

- **WHEN** a caller creates a transcription request using a non-empty local audio file path
- **THEN** the SDK-backed transcription runtime maps it internally to the Laravel AI SDK local-path transcription path
- **AND** existing prompt, language, diarization, timeout, provider, model, provider options, metadata, usage, and segment mapping behavior is preserved

#### Scenario: Storage source is supported

- **WHEN** a caller creates a transcription request using a storage path and optional disk name
- **THEN** the SDK-backed transcription runtime maps it internally to the Laravel AI SDK storage transcription path
- **AND** the caller does not need to download the object or convert it to base64 in application code

#### Scenario: Uploaded file source is supported

- **WHEN** a caller creates a transcription request using an uploaded audio file
- **THEN** the SDK-backed transcription runtime maps it internally to the Laravel AI SDK upload transcription path
- **AND** the source metadata is available to package fakes and observability without logging file contents

#### Scenario: Unsupported URL source fails closed

- **WHEN** a caller creates a transcription request using a URL audio source
- **AND** the installed Laravel AI SDK/provider path does not support remote audio transcription
- **THEN** Agent Kit throws a package-owned unsupported source exception before provider dispatch
- **AND** the exception message identifies the unsupported source kind and remediation

### Requirement: Existing base64 transcription API SHALL remain backward compatible

Existing callers that construct `TranscriptionRequest` with `base64Audio` MUST continue to work without source-object migration.

#### Scenario: Existing constructor call still works

- **WHEN** existing code constructs a valid `TranscriptionRequest` with `base64Audio`
- **THEN** validation passes
- **AND** the SDK-backed runtime behaves as it did before this change

#### Scenario: Ambiguous audio input fails

- **WHEN** a caller provides both `base64Audio` and an explicit audio source object
- **THEN** the request constructor fails with an invalid argument exception
- **AND** no provider request is dispatched

### Requirement: Audio source telemetry SHALL be redacted

Agent Kit MUST expose audio source kind and safe metadata while avoiding raw media payload disclosure.

#### Scenario: Base64 payload is redacted

- **WHEN** a transcription request with a base64 source is recorded by fakes, events, or logs
- **THEN** raw base64 content is not emitted in full
- **AND** source kind and optional MIME type remain available for assertions and diagnostics

#### Scenario: Storage/path metadata is safe

- **WHEN** a transcription request uses storage or local path input
- **THEN** package fakes can assert source kind, path, disk, and MIME type
- **AND** production observability applies existing redaction policy before emitting path-like values

### Requirement: Fakes SHALL support audio source assertions

Package fakes and test helpers MUST allow applications to assert that transcription was requested with the expected package-owned audio source kind.

#### Scenario: Assert storage transcription request

- **WHEN** a test uses the Agent Kit transcription fake
- **AND** application code requests transcription from storage
- **THEN** the test can assert that the recorded request used the storage source kind and expected disk/path metadata
