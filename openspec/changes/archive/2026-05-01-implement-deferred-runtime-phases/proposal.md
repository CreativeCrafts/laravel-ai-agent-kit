> **Superseded:** The deferred roadmap was implemented and documented under the archived change `openspec/changes/archive/2026-05-01-close-agent-kit-gaps/`. Prefer that archive and the package `CHANGELOG.md` / `UPGRADE.md` for the shipped program.
>
> **Archive (2026-05-01):** This folder was the duplicate planning change; it is kept only as historical context. Task checkboxes were never updated because execution tracked `close-agent-kit-gaps` instead.

## Why

`evolve-text-execution-surface` intentionally deferred several high-impact capabilities to keep the text-surface breaking window focused and low-risk. Before archiving that change, we need a dedicated follow-up proposal that captures all deferred work in one roadmap so implementation order can be decided explicitly instead of ad hoc.

## What Changes

- Create a single roadmap change that tracks all previously deferred items as scoped capabilities.
- Define requirements for streaming execution support (streamable responses and broadcast/event delivery).
- Define requirements for modality runtimes beyond text (transcription, embeddings, image generation, reranking).
- Define requirements for SDK middleware adoption across runtime execution paths.
- Define requirements for conversation-store convergence with Laravel AI contracts.
- Define requirements for attachment persistence and replay across conversation turns.
- Define requirements for migrating `TextToStructuredEvaluation` to first-class structured runtime output consumption.
- Include a phased implementation task plan that makes sequencing dependencies explicit and supports discussing implementation order.

## Capabilities

### New Capabilities
- `runtime-streaming`: Streaming runtime behavior, transport/event surface, and result semantics for partial output delivery.
- `modality-runtimes`: Runtime contracts and behavior for transcription, embeddings, image generation, and reranking.
- `runtime-middleware`: Middleware pipeline integration for runtime execution with deterministic ordering and failure propagation.
- `conversation-store-convergence`: Contract alignment between package conversation storage and Laravel AI conversation abstractions.
- `attachment-persistence`: Durable storage/replay model for request attachments across multi-turn conversations.
- `structured-evaluation-migration`: Blueprint/evaluation behavior that consumes runtime `structuredOutput` directly instead of normalizer-based shape repair.

### Modified Capabilities
<!-- None — openspec/specs/ currently has no canonical published specs; this change introduces new capability specs. -->

## Impact

- **Code areas (expected):** `src/Core/Runtime/`, `src/Core/Conversation/`, `src/Blueprints/`, `src/Contracts/`, `src/Support/`, streaming/broadcast integrations, and modality-specific runtime adapters.
- **Public APIs:** New streaming and modality entry points; conversation and attachment lifecycle semantics become more explicit.
- **Migration risk:** Medium-to-high due to cross-cutting runtime and storage changes; phased rollout is required.
- **Dependencies:** May require adopting additional Laravel AI SDK surfaces that were previously deferred; no new dependency is proposed until design finalizes concrete integration points.