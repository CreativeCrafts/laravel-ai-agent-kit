## Why

The `audit-laravel-ai-sdk-parity` sweep classified broad Laravel AI SDK modality event normalization as deferred. Agent Kit already exposes typed modality runtime contracts and DTOs for embeddings, image generation, audio generation, transcription, and reranking, but it does not emit package-normalized redacted events for every modality call.

Applications may be adequately served by return DTOs and direct Laravel AI SDK listeners. Before adding more package events, the project should evaluate whether modality events need package-owned redaction, telemetry naming, and fake/test support.

## What Changes

- Review current modality runtime usage and SDK modality event surfaces.
- Decide which modality events, if any, should become package-normalized events.
- Keep direct-SDK modality event usage documented when package normalization adds no clear value.
- Add specs/tasks for any approved package-owned modality event normalization work.

## Capabilities

### Modified Capabilities
- `sdk-parity-governance`: Deferred SDK event gaps have a documented evaluation path.
- `events-and-telemetry`: Package-normalized modality event decisions are explicit.

## Impact

- **Code areas:** modality runtimes, observability events, docs, tests.
- **Public API:** Potential new package events only if the evaluation approves them.
- **Migration risk:** Low; evaluation proposal first.
