## Why

Agent Kit intentionally wraps selected Laravel AI SDK capabilities behind package-owned contracts, policy, telemetry, provider selection, fakes, and workflow surfaces. The existing SDK parity governance classifies package-owned gaps as capabilities that need Agent Kit wrappers when workflows, provider policy, telemetry, authorization, or testing fakes matter.

A real gap remains in transcription input handling: Agent Kit's `TranscriptionRequest` accepts only `base64Audio`, and `SdkTranscriptionRuntime` always delegates to `Laravel\Ai\Transcription::fromBase64(...)`. The underlying Laravel AI SDK already supports richer transcription audio sources through `Transcription::fromBase64(...)`, `fromPath(...)`, `fromStorage(...)`, and `fromUpload(...)`, while queued SDK transcription also explicitly supports local and stored audio. Applications with existing stored/local audio should not need to convert media to base64 or call the underlying SDK directly just to stay inside Agent Kit.

A second workflow gap exists for audio-image structured evaluation. Agent Kit supports transcription, structured runtime execution, schema-backed responses, and image attachments through `ExecutionRequest`, but there is no package-owned blueprint/pipeline that composes these into an idiomatic workflow for providers such as OpenAI that can evaluate transcript text together with an image and return strict structured output.

This change closes both gaps by adding package-owned audio source abstractions and a multimodal audio-image structured evaluation workflow that uses Agent Kit contracts end-to-end while keeping Laravel AI SDK calls inside SDK bridge implementations.

## What Changes

- Add a package-owned `TranscriptionAudioSource` abstraction that supports base64, local path, storage disk/path, uploaded file, and provider-supported URL where verified.
- Extend transcription requests and the SDK transcription runtime so Agent Kit can delegate to the matching Laravel AI SDK transcription constructor without application code importing or calling `Laravel\Ai\Transcription` directly.
- Preserve existing prompted transcription, provider options, language, diarization, timeout, provider/model selection, fake/assertion behavior, and metadata mapping.
- Add a package-owned multimodal audio-image structured evaluation workflow that composes transcription plus structured image/text evaluation through Agent Kit runtimes.
- Support caller-provided structured schemas for the evaluation stage.
- Support provider capability gating so the workflow fails closed when the selected provider does not support both image input and structured output.
- Add tests and docs proving applications can implement audio-image structured scoring using Agent Kit only.

## Capabilities

### New Capabilities

- `transcription-audio-sources`: Agent Kit transcription accepts package-owned audio source objects for base64, local path, storage, upload, and verified URL support while hiding Laravel AI SDK constructors from application code.
- `multimodal-audio-image-structured-evaluation`: Agent Kit exposes a workflow/pipeline for audio transcription followed by image-plus-transcript structured evaluation.

### Modified Capabilities

- `modality-runtimes`: Transcription runtime input support is widened from base64-only to package-owned audio source variants.
- `blueprint-workflows`: Audio evaluation workflows can include multimodal attachments in the evaluation stage.
- `developer-documentation`: Docs explain the Agent Kit-first path for stored/local audio transcription and multimodal audio-image evaluation, avoiding direct SDK usage.
- `testing-fakes`: Fakes and assertions cover audio source transcription and multimodal evaluation requests.

## Impact

- **Code areas:** `src/Core/Modality/**`, `src/Contracts/Modality/**`, `src/Blueprints/**` or `src/Core/Pipeline/**`, `src/Core/Runtime/**`, package fakes, config validation, docs, tests, and maintainer SDK matrix.
- **Public API:** Additive. Existing `TranscriptionRequest` base64 construction must continue to work. New request constructors or new source objects provide the preferred API.
- **Dependencies:** Continue to target `laravel/ai ^0.6`. Implementation must verify exact SDK method availability before shipping source variants.
- **Migration risk:** Low to medium. Existing base64 users remain compatible; richer sources introduce filesystem/upload validation and provider support decisions.
- **Operational risk:** Reduced application-level media conversion and reduced direct-SDK drift; provider capability failures become explicit.

## Non-Goals

- Do not expose Laravel AI SDK file/audio objects as Agent Kit public request types.
- Do not mirror every SDK file abstraction one-for-one unless it is needed for transcription or multimodal evaluation.
- Do not implement provider-specific OpenAI request payloads in application-facing APIs.
- Do not add streaming structured evaluation; the current runtime rejects structured streaming and this workflow requires strict structured output.
- Do not replace existing queued pipeline infrastructure; this change composes with it.
