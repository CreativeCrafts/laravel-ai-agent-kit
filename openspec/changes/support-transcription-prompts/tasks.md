## 1. Request DTO and validation

- [x] 1.1 Add nullable `prompt` support to `TranscriptionRequest` as an additive constructor field after `metadata`.
- [x] 1.2 Validate that `prompt` is either `null` or a non-empty string after trimming.
- [x] 1.3 Preserve all existing validation for `runId`, `base64Audio`, and `timeout`.
- [x] 1.4 Add tests proving existing request construction without prompt remains unchanged.
- [x] 1.5 Add tests proving blank prompt values fail request validation.

## 2. SDK runtime forwarding

- [x] 2.1 Audit the installed `laravel/ai` transcription pending API and record the supported prompt method name in the implementation notes or changelog.
- [x] 2.2 Forward `TranscriptionRequest::$prompt` from `SdkTranscriptionRuntime` when the pending transcription object supports prompt forwarding.
- [x] 2.3 Add a package exception for unsupported prompted transcription when the SDK/runtime cannot honor the prompt.
- [x] 2.4 Ensure prompted transcription fails before provider dispatch when no supported SDK prompt method exists.
- [x] 2.5 Preserve existing language, diarization, timeout, provider, model, result mapping, usage mapping, segment mapping, and metadata behavior.

## 3. Blueprint/workflow integration

- [x] 3.1 Identify transcription blueprints/workflows that render or reference transcription prompts.
- [x] 3.2 Pass rendered transcription prompt text into `TranscriptionRequest::$prompt` for those workflows.
- [x] 3.3 Preserve prompt name/version metadata for observability.
- [x] 3.4 Add or reuse a render-only prompt service if existing prompt mapping cannot produce prompt text without a text-generation request.
- [x] 3.5 Preserve behavior for workflows with no configured transcription prompt.

## 4. Testing and fakes

- [x] 4.1 Add runtime tests proving prompted requests are forwarded when supported.
- [x] 4.2 Add runtime tests proving unsupported prompted requests throw the package exception.
- [x] 4.3 Add fake/assertion coverage so applications can assert the last transcription prompt without live provider calls.
- [x] 4.4 Add blueprint tests proving rendered prompts are passed to transcription requests.
- [x] 4.5 Add regression tests for unprompted transcription requests.

## 5. Documentation

- [x] 5.1 Update modality/transcription docs with the `prompt` request field and examples.
- [x] 5.2 Document provider/SDK compatibility and fail-fast behavior.
- [x] 5.3 Update testing docs with fake/assertion guidance for transcription prompts.
- [x] 5.4 Update changelog with the additive API and any SDK version compatibility notes.

## 6. Validation

- [x] 6.1 Run `openspec validate support-transcription-prompts`.
- [x] 6.2 Run formatting checks.
- [x] 6.3 Run static analysis.
- [x] 6.4 Run modality/transcription runtime tests.
- [x] 6.5 Run blueprint tests affected by audio transcription.
- [x] 6.6 Run the full test suite if feasible.
