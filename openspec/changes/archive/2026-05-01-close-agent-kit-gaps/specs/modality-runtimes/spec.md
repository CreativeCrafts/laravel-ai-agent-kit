## ADDED Requirements

### Requirement: Runtime SHALL expose transcription execution
The package MUST expose a transcription runtime path that accepts supported audio input references and returns deterministic transcription output metadata.

#### Scenario: Audio transcription request succeeds
- **WHEN** a caller submits a valid transcription request with supported audio input
- **THEN** the runtime returns a transcription result object containing normalized transcript text and provider metadata

### Requirement: Runtime SHALL expose embeddings execution
The package MUST expose an embeddings runtime path that accepts one or more text inputs and returns embedding vectors with stable ordering matching input ordering.

#### Scenario: Batch embeddings preserve input order
- **WHEN** a caller submits a batch embeddings request
- **THEN** the runtime returns embedding vectors in the same order as the submitted input items

### Requirement: Runtime SHALL expose image generation and reranking execution
The package MUST expose dedicated runtime paths for image generation and reranking, each with request and response objects specific to that modality.

#### Scenario: Modality-specific runtime selection
- **WHEN** a caller executes an image generation or reranking request
- **THEN** the package routes execution through the modality-specific runtime contract and returns a modality-specific result object