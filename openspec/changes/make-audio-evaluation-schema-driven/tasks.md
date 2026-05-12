## 1. Request and result contract

- [ ] 1.1 Add optional schema support to `AudioToTextToEvaluationRequest`.
- [ ] 1.2 Support schema forms aligned with runtime structured output where practical: closure, `ObjectSchema`, and supported class-string schemas.
- [ ] 1.3 Update `AudioToTextToEvaluationResult` to expose transcript, structured output, segments, provider/model metadata, usage metadata, and compatibility fields.
- [ ] 1.4 Preserve existing no-schema/default behavior for current callers.
- [ ] 1.5 Add tests for request/result serialization or `toArray()` compatibility.

## 2. Blueprint workflow

- [ ] 2.1 Keep transcription as the first stage and preserve transcript text.
- [ ] 2.2 Preserve diarized/transcription segments in the final result.
- [ ] 2.3 Build the evaluation-stage `ExecutionRequest` with the caller-provided schema when present.
- [ ] 2.4 Require structured output when a custom schema is provided.
- [ ] 2.5 Preserve existing default evaluation path when schema is omitted.

## 3. Failure behavior

- [ ] 3.1 Add tests for invalid schema failures.
- [ ] 3.2 Ensure schema/evaluation failures identify the evaluation stage.
- [ ] 3.3 Ensure transcription failures identify the transcription stage and skip evaluation.
- [ ] 3.4 Preserve previous exceptions where runtime/schema exceptions are wrapped.

## 4. Testing

- [ ] 4.1 Add tests for class-string schema output.
- [ ] 4.2 Add tests for `ObjectSchema` output.
- [ ] 4.3 Add tests for closure schema output.
- [ ] 4.4 Add tests for diarized segments included in the final result.
- [ ] 4.5 Add tests proving existing no-schema behavior remains compatible.
- [ ] 4.6 Add fake-driven tests that do not require live providers.

## 5. Documentation and changelog

- [ ] 5.1 Update `docs/blueprints.md` with schema-driven audio evaluation examples.
- [ ] 5.2 Update `docs/streaming-and-modalities.md` if transcription metadata guidance changes.
- [ ] 5.3 Update `README.md` quick-start only if the public example should show schema-driven output.
- [ ] 5.4 Update `CHANGELOG.md`.

## 6. Validation

- [ ] 6.1 Run `openspec validate make-audio-evaluation-schema-driven`.
- [ ] 6.2 Run formatting checks.
- [ ] 6.3 Run PHPStan/static analysis.
- [ ] 6.4 Run audio blueprint test subset.
- [ ] 6.5 Run full test suite if feasible.
