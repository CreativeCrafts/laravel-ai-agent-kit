## Why

Prior audits identified a **split between shipped code and documented or spec’d behavior**: flagship blueprints still parse JSON from unstructured text while the runtime exposes `structuredOutput`; `composer.json` and README overstate observability and workflow coverage; CI badge URLs in README do not match this repository’s workflows; the vector binding only supports `in_memory`; and the deferred roadmap (`implement-deferred-runtime-phases`) remains largely unimplemented. This change consolidates **all closure work** into one OpenSpec-tracked program so sequencing, acceptance criteria, and upgrade notes stay coherent.

## What Changes

- **Structured evaluation migration:** `TextToStructuredEvaluation` (and related blueprint agents) SHALL consume `ExecutionResult.structuredOutput` as the primary path, with bounded fallback and observability per `structured-evaluation-migration` spec.
- **Runtime middleware:** Introduce a deterministic middleware pipeline applied consistently across direct `AiRuntime`, blueprint compilation/run, and orchestrator-invoked runtime paths (`runtime-middleware` spec).
- **Streaming:** Add stream-oriented text execution, package-level stream events, and optional broadcast/event forwarding (`runtime-streaming` spec).
- **Modality runtimes:** Add contract-first APIs and SDK-backed adapters for transcription, embeddings, image generation, and reranking (`modality-runtimes` spec); align blueprint transcription stages where appropriate.
- **Conversation store convergence:** Align package conversation persistence with Laravel AI conversation expectations, including a backward-compatible read bridge (`conversation-store-convergence` spec).
- **Attachment persistence:** Persist attachment references on messages, enforce replay policy (retention, expiry, authorization), and integrate with the memory bridge after convergence foundations exist (`attachment-persistence` spec).
- **Package hardening:** Close documentation and packaging gaps (README/CI alignment, `composer.json` description accuracy, vector store strategy beyond in-memory, testability for `structuredOutput` under fakes) (`package-hardening` spec).
- **Rollout documentation:** Update `UPGRADE.md`, README, and release notes per phase (`documentation-rollout` tasks).

## Capabilities

### New Capabilities

- `package-hardening`: Documentation accuracy, Packagist/composer messaging, vector storage strategy, and developer-facing test ergonomics for structured output under Laravel AI fakes.
- `documentation-rollout`: `UPGRADE.md`, README, and changelog/release notes stay aligned with each shipped phase.

### Carried Capability Specs (from `implement-deferred-runtime-phases`)

The following spec directories are **included verbatim** under `specs/` and are satisfied when their requirements are implemented and tested:

- `structured-evaluation-migration`
- `runtime-middleware`
- `runtime-streaming`
- `modality-runtimes`
- `conversation-store-convergence`
- `attachment-persistence`

## Impact

- **Public API:** New streaming and modality entry points; possible config keys for middleware order, streaming transport, attachment replay, and vector driver selection; blueprint behavior change when structured output becomes primary.
- **Breaking risk:** Medium — mitigated by phased rollout, feature flags where appropriate, and explicit `UPGRADE.md` sections per phase.
- **Dependencies:** Continues to rely on `laravel/ai`; concrete modality and streaming integration points are finalized in `design.md` during implementation spikes.

## Non-Goals

- Redesigning multi-agent orchestration delegation semantics beyond what is required to thread middleware or streaming consistently.
- Replacing Laravel AI as the sole provider integration layer.
