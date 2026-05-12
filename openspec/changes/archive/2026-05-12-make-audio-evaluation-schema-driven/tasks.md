## 1. Request and result contract

- [x] 1.1 Add optional schema support to `AudioToTextToEvaluationRequest`.
- [x] 1.2 Support schema forms aligned with runtime structured output where practical: closure, `ObjectSchema`, and supported class-string schemas.
- [x] 1.3 Update `AudioToTextToEvaluationResult` to expose transcript, structured output, segments, provider/model metadata, usage metadata, and compatibility fields.
- [x] 1.4 Preserve existing no-schema/default behavior for current callers.
- [x] 1.5 Add tests for request/result serialization or `toArray()` compatibility.

## 2. Blueprint workflow

- [x] 2.1 Keep transcription as the first stage and preserve transcript text.
- [x] 2.2 Preserve diarized/transcription segments in the final result.
- [x] 2.3 Build the evaluation-stage `ExecutionRequest` with the caller-provided schema when present.
- [x] 2.4 Require structured output when a custom schema is provided.
- [x] 2.5 Preserve existing default evaluation path when schema is omitted.

## 3. Failure behavior

- [x] 3.1 Add tests for invalid schema failures.
- [x] 3.2 Ensure schema/evaluation failures identify the evaluation stage.
- [x] 3.3 Ensure transcription failures identify the transcription stage and skip evaluation.
- [x] 3.4 Preserve previous exceptions where runtime/schema exceptions are wrapped.

## 4. Testing

- [x] 4.1 Add tests for class-string schema output.
- [x] 4.2 Add tests for `ObjectSchema` output.
- [x] 4.3 Add tests for closure schema output.
- [x] 4.4 Add tests for diarized segments included in the final result.
- [x] 4.5 Add tests proving existing no-schema behavior remains compatible.
- [x] 4.6 Add fake-driven tests that do not require live providers.

## 5. Documentation and changelog

- [x] 5.1 Update `docs/blueprints.md` with schema-driven audio evaluation examples.
- [x] 5.2 Update `docs/streaming-and-modalities.md` if transcription metadata guidance changes.
- [x] 5.3 Update `README.md` quick-start only if the public example should show schema-driven output.
- [x] 5.4 Update `CHANGELOG.md`.

## 6. Validation

- [x] 6.1 Run `openspec validate make-audio-evaluation-schema-driven`.
- [x] 6.2 Run formatting checks.
- [x] 6.3 Run PHPStan/static analysis.
- [x] 6.4 Run audio blueprint test subset.
- [x] 6.5 Run full test suite if feasible.
