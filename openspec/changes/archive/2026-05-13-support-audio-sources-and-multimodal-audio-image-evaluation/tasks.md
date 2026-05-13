## 1. SDK verification

- [x] 1.1 Verify the installed `laravel/ai ^0.6` source exposes transcription constructors for base64, local path, storage, and upload.
- [x] 1.2 Verify whether the installed SDK/provider path supports remote URL audio transcription; document the result and defer URL execution if unsupported.
- [x] 1.3 Verify the installed SDK image abstraction supports URL, base64, local path, storage, and upload image inputs for runtime attachments.
- [x] 1.4 Verify structured execution still maps schema-backed SDK responses into `ExecutionResult::$structuredOutput`.
- [x] 1.5 Record verification notes in the design or maintainer SDK capability matrix.

## 2. Transcription audio source API

- [x] 2.1 Add `TranscriptionAudioSourceKind` enum.
- [x] 2.2 Add immutable `TranscriptionAudioSource` DTO with factories for base64, path, storage, upload, and URL.
- [x] 2.3 Add validation for non-empty payloads, MIME type, disk, URL shape, and upload instances.
- [x] 2.4 Extend `TranscriptionRequest` to accept `TranscriptionAudioSource` while preserving existing base64 constructor compatibility.
- [x] 2.5 Add `TranscriptionRequest::fromAudioSource(...)` named constructor.
- [x] 2.6 Fail fast when both legacy `base64Audio` and explicit `audioSource` are provided.
- [x] 2.7 Update PHPDoc and static-analysis annotations.

## 3. SDK transcription runtime mapping

- [x] 3.1 Refactor `SdkTranscriptionRuntime` to create pending transcription requests from source kind.
- [x] 3.2 Map base64 sources to `Transcription::fromBase64(...)`.
- [x] 3.3 Map local path sources to `Transcription::fromPath(...)`.
- [x] 3.4 Map storage sources to `Transcription::fromStorage(...)`.
- [x] 3.5 Map upload sources to `Transcription::fromUpload(...)`.
- [x] 3.6 For URL sources, either implement verified SDK/provider support or throw `UnsupportedTranscriptionAudioSourceException` before provider dispatch.
- [x] 3.7 Preserve prompt, provider options, language, diarization, timeout, provider/model, result mapping, usage mapping, segments, and metadata.

## 4. Transcription fakes and observability

- [x] 4.1 Update transcription fake recording to include audio source kind and safe source metadata.
- [x] 4.2 Ensure base64 content and upload contents are never logged or emitted in full.
- [x] 4.3 Add fake assertions for source kind, disk/path, MIME type, prompt, provider, and model.
- [x] 4.4 Add event/redaction tests for source metadata.

## 5. Multimodal evaluation input API

- [x] 5.1 Add `EvaluationImageInputKind` enum.
- [x] 5.2 Add immutable `EvaluationImageInput` DTO with factories for URL, base64, path, storage, and upload.
- [x] 5.3 Add validation for image source payloads.
- [x] 5.4 Add internal mapper from `EvaluationImageInput` to Laravel AI SDK image attachment objects.
- [x] 5.5 Ensure SDK image objects remain internal to Agent Kit bridge code.

## 6. Audio-image structured evaluation workflow

- [x] 6.1 Add `AudioImageStructuredEvaluationRequest` DTO.
- [x] 6.2 Add `AudioImageStructuredEvaluationResult` DTO.
- [x] 6.3 Add package exceptions for unsupported provider capability, unsupported audio source, unsupported image source, and empty transcript.
- [x] 6.4 Implement `AudioImageStructuredEvaluation` blueprint/workflow using `TranscriptionRuntime` and `AiRuntime`.
- [x] 6.5 Build evaluation-stage `ExecutionRequest` with transcript text, instructions, caller schema, generation options, provider/model, metadata, and image attachment.
- [x] 6.6 Prefer `ExecutionResult::$structuredOutput` and expose raw output as secondary metadata.
- [x] 6.7 Include stage metadata for transcription and evaluation providers/models/usage/source kinds.
- [x] 6.8 Add `allowEmptyTranscript` request option and enforce default rejection.

## 7. Provider capability validation

- [x] 7.1 Define canonical capability names for audio transcription, image input/vision, and structured output.
- [x] 7.2 Add provider capability checker or extend existing provider selector validation.
- [x] 7.3 Fail closed before dispatch when configured provider metadata proves unsupported capability.
- [x] 7.4 Document fallback behavior when provider capability metadata is unavailable.
- [x] 7.5 Add tests for supported provider, unsupported transcription provider, unsupported image provider, and unknown capability metadata.

## 8. Pipeline and queued-pipeline integration

- [x] 8.1 Add direct facade/manager entry point for audio-image structured evaluation if consistent with existing Agent Kit facade ergonomics.
- [x] 8.2 Add pipeline step definitions or a queued pipeline wrapper for the workflow.
- [x] 8.3 Ensure queued payloads serialize only Agent Kit DTOs/scalars and not Laravel AI SDK objects.
- [x] 8.4 Add tests for synchronous blueprint execution.
- [x] 8.5 Add tests for queued pipeline dispatch payload shape.

## 9. Documentation

- [x] 9.1 Update transcription modality docs with `TranscriptionAudioSource` examples for base64, path, storage, upload, and URL behavior.
- [x] 9.2 Update multimodal/blueprint docs with audio-image structured evaluation examples.
- [x] 9.3 Document OpenAI-style provider requirements without hard-coding OpenAI-only APIs into public package contracts.
- [x] 9.4 Update testing docs with fakes/assertions for source transcription and multimodal evaluation.
- [x] 9.5 Update maintainer SDK capability matrix to mark richer transcription sources and multimodal audio-image structured evaluation as package-owned supported surfaces.
- [x] 9.6 Update `CHANGELOG.md` with additive API notes; `UPGRADE.md` is intentionally absent for this pre-release package line.

## 10. Validation

- [x] 10.1 Run `openspec validate support-audio-sources-and-multimodal-audio-image-evaluation`.
- [x] 10.2 Run `composer pint` or package formatting command.
- [x] 10.3 Run static analysis.
- [x] 10.4 Run modality/transcription runtime tests.
- [x] 10.5 Run blueprint/workflow tests.
- [x] 10.6 Run queued pipeline tests.
- [x] 10.7 Run full test suite if feasible.
