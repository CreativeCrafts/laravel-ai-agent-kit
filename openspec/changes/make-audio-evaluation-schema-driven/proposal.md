## Why

`AudioToTextToEvaluation` is currently too opinionated for the intended package direction. It behaves like a fixed evaluation preset with a narrow result DTO instead of a reusable workflow primitive where the application decides the structured output shape.

Real applications evaluate audio for different business outcomes: support QA, compliance, sales qualification, coaching, incident triage, medical intake summaries, legal call review, and many others. A single package-owned result shape cannot represent those needs without becoming brittle or forcing users to post-process package-specific fields.

Agent Kit already supports schema-driven structured runtime execution elsewhere. The audio evaluation blueprint should follow that pattern: transcribe audio, then evaluate the transcript against a caller-provided schema.

## What Changes

- Make `AudioToTextToEvaluation` schema-driven.
- Allow `AudioToTextToEvaluationRequest` to accept user-defined structured output schemas using the same schema forms supported by runtime execution where practical:
  - `Closure`
  - `ObjectSchema`
  - `class-string` implementing Laravel AI structured output contracts
- Return a result that exposes:
  - transcript text;
  - optional diarized/transcription segments;
  - structured output matching the caller-provided schema;
  - provider/model/usage metadata for transcription and evaluation stages.
- Preserve existing default evaluation behavior as a backward-compatible preset or compatibility path.
- Document the difference between the schema-driven workflow primitive and any default/opinionated preset.
- Add tests for custom schemas, legacy/default behavior, transcription metadata, and fake-driven execution.

## Capabilities

### Modified Capabilities
- `blueprints`: Audio-to-text-to-evaluation workflows support caller-defined structured result schemas.
- `streaming-and-modalities`: Audio transcription metadata and diarized segments can feed custom structured evaluation outputs.
- `sdk-parity-governance`: Blueprint APIs align with package-owned structured output patterns instead of fixed SDK/provider payloads.

## Impact

- **Code areas:** audio blueprint request/result DTOs, coordinator/evaluation flow, docs, facade examples, tests.
- **Public API:** Additive schema-driven request fields and result fields. Existing default result behavior should remain compatible or move behind a named compatibility preset.
- **Risk:** Medium. Result DTO changes must avoid breaking current consumers unexpectedly.
- **Migration:** Existing callers should continue working through defaults; new callers can opt into schema-driven output.
