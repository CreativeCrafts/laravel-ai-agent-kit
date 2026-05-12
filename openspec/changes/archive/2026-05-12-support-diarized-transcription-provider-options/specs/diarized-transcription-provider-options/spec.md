## ADDED Requirements

### Requirement: Transcription requests SHALL expose controlled provider options for diarized transcription

Agent Kit SHALL provide an additive, typed way to request supported provider-specific diarized transcription options without exposing arbitrary provider payloads.

#### Scenario: Requesting automatic chunking for diarized OpenAI transcription

- **GIVEN** a transcription request using an OpenAI diarization-capable transcription model
- **AND** diarization is enabled
- **AND** provider options request `chunkingStrategy=auto`
- **WHEN** the transcription runtime executes the request
- **THEN** the provider request SHALL include automatic chunking where the underlying runtime supports it
- **AND** the response SHALL still map transcript text and speaker segments into `TranscriptionResult`

#### Scenario: Existing transcription requests omit provider options

- **GIVEN** a transcription request without provider options
- **WHEN** the transcription runtime executes the request
- **THEN** existing language, diarization, timeout, provider, model, and result-mapping behavior SHALL remain unchanged

### Requirement: Unsupported diarized transcription options SHALL fail early

Agent Kit SHALL validate controlled transcription provider options before dispatching a provider request.

#### Scenario: Unsupported chunking strategy value

- **GIVEN** a transcription request with a chunking strategy value other than `auto`
- **WHEN** the transcription runtime validates the request
- **THEN** it SHALL fail before provider dispatch
- **AND** the error SHALL identify the unsupported chunking strategy

#### Scenario: Chunking requested without diarization

- **GIVEN** a transcription request with `chunkingStrategy=auto`
- **AND** diarization is disabled
- **WHEN** the transcription runtime validates the request
- **THEN** it SHALL fail before provider dispatch
- **AND** the error SHALL explain that chunking is only supported for diarized transcription in this package surface

#### Scenario: Chunking requested for an unsupported provider path

- **GIVEN** a transcription request with `chunkingStrategy=auto`
- **AND** the resolved provider/runtime cannot support that option
- **WHEN** the transcription runtime validates the request
- **THEN** it SHALL fail before provider dispatch
- **AND** the error SHALL identify that the selected provider path does not support the option

### Requirement: Diarized transcription documentation SHALL define the SDK boundary

Public docs SHALL explain how Agent Kit transcription relates to Laravel AI SDK transcription support and provider-specific diarization requirements.

#### Scenario: Developer reads modality documentation

- **GIVEN** a developer wants to use `gpt-4o-transcribe-diarize`
- **WHEN** they read the streaming/modalities documentation
- **THEN** the docs SHALL state that it is a transcription model, not a chat/runtime model
- **AND** the docs SHALL explain when Laravel AI SDK diarization is sufficient
- **AND** the docs SHALL explain when Agent Kit controlled provider options are required for longer diarized audio
