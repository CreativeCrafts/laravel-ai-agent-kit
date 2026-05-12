## 1. Request DTO and validation

- [ ] 1.1 Add a typed transcription provider-options DTO or equivalent additive request field.
- [ ] 1.2 Support `chunkingStrategy=auto` as the first controlled option.
- [ ] 1.3 Reject unsupported chunking strategy values before provider dispatch.
- [ ] 1.4 Reject chunking options when diarization is disabled.
- [ ] 1.5 Add tests for unchanged existing transcription requests without provider options.

## 2. Runtime support

- [ ] 2.1 Audit the installed Laravel AI SDK transcription API for provider-option passthrough support at implementation time.
- [ ] 2.2 If SDK passthrough exists, wire Agent Kit controlled options through the SDK path.
- [ ] 2.3 If SDK passthrough does not exist, implement a package-owned OpenAI diarized transcription bridge for supported options.
- [ ] 2.4 Ensure non-OpenAI or unsupported provider paths fail fast when controlled options cannot be honored.
- [ ] 2.5 Preserve existing result mapping into `TranscriptionResult` and `TranscriptionSegmentResult`.

## 3. Testing and fakes

- [ ] 3.1 Add tests proving diarized OpenAI transcription can request `chunkingStrategy=auto` without live provider calls.
- [ ] 3.2 Add tests proving unsupported option values fail before provider dispatch.
- [ ] 3.3 Add tests proving chunking without diarization fails before provider dispatch.
- [ ] 3.4 Add tests proving package fakes or SDK fakes can assert transcription provider options.

## 4. Documentation and changelog

- [ ] 4.1 Update `docs/streaming-and-modalities.md` with diarized transcription guidance.
- [ ] 4.2 Update `docs/providers.md` or `docs/configuration.md` if provider/model configuration needs examples.
- [ ] 4.3 Update `docs/testing.md` with fake/assertion guidance for transcription provider options.
- [ ] 4.4 Update `CHANGELOG.md`.

## 5. Validation

- [ ] 5.1 Run `openspec validate support-diarized-transcription-provider-options`.
- [ ] 5.2 Run formatting checks.
- [ ] 5.3 Run PHPStan/static analysis.
- [ ] 5.4 Run transcription runtime test subset.
- [ ] 5.5 Run full test suite if feasible.
