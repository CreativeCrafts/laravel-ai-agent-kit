## Why

OpenAI's `gpt-4o-transcribe-diarize` model can produce speaker-labeled transcription segments, but it has provider-specific requirements that are not fully represented by the current Agent Kit transcription DTO.

Laravel AI SDK currently exposes transcription language, diarization, timeout, provider, and model. For OpenAI, it maps `diarize()` to `response_format=diarized_json`, which is enough for short diarized transcription calls. However, OpenAI requires `chunking_strategy` for `gpt-4o-transcribe-diarize` inputs longer than 30 seconds, and Laravel AI SDK does not currently expose a generic transcription provider-options passthrough.

Agent Kit needs a safe package-owned way to express diarized transcription options without turning transcription requests into an unbounded provider payload escape hatch.

## What Changes

- Extend Agent Kit transcription requests with controlled provider options for diarized transcription.
- Support OpenAI diarized transcription chunking, starting with `chunking_strategy=auto`.
- Preserve existing `diarize`, `language`, `timeout`, `provider`, and `model` behavior.
- Keep provider-specific options explicit, documented, and validated.
- Add tests proving the package can request diarized transcription with chunking options when the underlying runtime supports them.
- Document when the current Laravel AI SDK path is sufficient and when a package/provider-specific path is required.

## Capabilities

### Modified Capabilities
- `streaming-and-modalities`: Transcription runtime requests can represent provider-specific diarized transcription options safely.
- `sdk-parity-governance`: Direct SDK limitations around transcription provider options are documented and bridged only where package-owned behavior is needed.

## Impact

- **Code areas:** transcription DTOs, SDK transcription runtime, config/docs, tests.
- **Public API:** Additive transcription request fields or option object.
- **Provider behavior:** OpenAI diarized transcription can request chunking for longer audio.
- **Risk:** Low to medium. The design must avoid arbitrary unvalidated provider payloads while still supporting required OpenAI diarization options.
