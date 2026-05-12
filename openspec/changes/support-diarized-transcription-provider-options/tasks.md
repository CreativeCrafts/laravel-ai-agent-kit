## 1. Request DTO and validation

- [x] 1.1 Add a typed transcription provider-options DTO or equivalent additive request field.
- [x] 1.2 Support `chunkingStrategy=auto` as the first controlled option.
- [x] 1.3 Reject unsupported chunking strategy values before provider dispatch.
- [x] 1.4 Reject chunking options when diarization is disabled.
- [x] 1.5 Add tests for unchanged existing transcription requests without provider options.

## 2. Runtime support

- [x] 2.1 Audit the installed Laravel AI SDK transcription API for provider-option passthrough support at implementation time.
- [x] 2.2 If SDK passthrough exists, wire Agent Kit controlled options through the SDK path.
- [x] 2.3 If SDK passthrough does not exist, implement a package-owned OpenAI diarized transcription bridge for supported options.
- [x] 2.4 Ensure non-OpenAI or unsupported provider paths fail fast when controlled options cannot be honored.
- [x] 2.5 Preserve existing result mapping into `TranscriptionResult` and `TranscriptionSegmentResult`.

## 3. Testing and fakes

- [x] 3.1 Add tests proving diarized OpenAI transcription can request `chunkingStrategy=auto` without live provider calls.
- [x] 3.2 Add tests proving unsupported option values fail before provider dispatch.
- [x] 3.3 Add tests proving chunking without diarization fails before provider dispatch.
- [x] 3.4 Add tests proving package fakes or SDK fakes can assert transcription provider options.

## 4. Documentation and changelog

- [x] 4.1 Update `docs/streaming-and-modalities.md` with diarized transcription guidance.
- [x] 4.2 Update `docs/providers.md` or `docs/configuration.md` if provider/model configuration needs examples.
- [x] 4.3 Update `docs/testing.md` with fake/assertion guidance for transcription provider options.
- [x] 4.4 Update `CHANGELOG.md`.

## 5. Validation

- [x] 5.1 Run `openspec validate support-diarized-transcription-provider-options`.
- [x] 5.2 Run formatting checks.
- [x] 5.3 Run PHPStan/static analysis.
- [x] 5.4 Run transcription runtime test subset.
- [x] 5.5 Run full test suite if feasible.
