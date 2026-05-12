## Why

Applications using Agent Kit transcription sometimes need a domain-specific transcription prompt to preserve the spoken features that downstream workflows depend on. For example, language assessment workflows may need the transcription stage to preserve hesitations, pauses, false starts, uncertainty markers, grammar mistakes, Swedish characters, and spoken-language phrasing instead of silently normalizing the transcript.

Agent Kit currently owns transcription as a package runtime, but `TranscriptionRequest` only exposes audio, MIME type, language, diarization, timeout, provider, model, and metadata. `SdkTranscriptionRuntime` forwards only those fields to Laravel AI SDK. Callers that need a custom transcription prompt must either bypass Agent Kit and use the SDK/provider directly or accept transcription drift.

This creates a gap for package-owned workflows such as audio-to-text-to-evaluation: the prompt repository can govern transcription intent, but the modality runtime has no first-class way to carry that prompt into the SDK/provider transcription call.

## What Changes

- Add an optional `prompt` field to `TranscriptionRequest`.
- Validate that transcription prompts are either `null` or non-empty strings.
- Forward the prompt from `SdkTranscriptionRuntime` into Laravel AI SDK transcription calls when the installed SDK supports a prompt/instructions method.
- Fail fast with a clear package exception when a prompt is provided but the installed SDK transcription pending object cannot honor it.
- Preserve existing transcription behavior when no prompt is supplied.
- Update audio transcription blueprints/workflows to pass rendered transcription prompts into `TranscriptionRequest` where applicable.
- Add fake/test support so callers can assert the requested transcription prompt without live provider calls.
- Document prompt support, provider compatibility, and the fallback/fail-fast behavior.

## Capabilities

### Modified Capabilities
- `modality-runtimes`: Transcription runtime requests can carry optional custom transcription prompts and must not silently drop them.
- `sdk-parity-governance`: Laravel AI SDK transcription prompt support is audited and bridged through Agent Kit only when the SDK/provider path can honor the prompt.

## Impact

- **Code areas:** `TranscriptionRequest`, `SdkTranscriptionRuntime`, transcription facade path, audio transcription blueprints, tests, docs.
- **Public API:** Additive constructor field on `TranscriptionRequest`; no behavior change for callers that omit `prompt`.
- **Behavior:** Custom transcription prompts become package-owned and testable. Unsupported SDK/provider paths fail explicitly instead of producing silently unprompted transcripts.
- **Risk:** Low to medium. The main risk is Laravel AI SDK version variance; implementation must audit the installed SDK transcription API and guard prompt forwarding defensively.
