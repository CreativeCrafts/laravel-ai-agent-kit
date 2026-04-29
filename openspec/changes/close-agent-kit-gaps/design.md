## Context

The kit already ships `ExecutionRequest` / `ExecutionResult` (including `structuredOutput`), `SdkAiRuntime`, blueprint compilation, synchronous orchestration, queued **pipelines**, memory drivers (in-memory, Redis, database), and an in-memory `VectorStoreInterface` binding. Gaps fall into three buckets:

1. **Blueprint / spec drift:** `TextToStructuredEvaluationSpecialistAgent` still normalizes JSON from `$runtimeResult->output` instead of preferring `$runtimeResult->structuredOutput` with a schema-backed call, contradicting `structured-evaluation-migration/spec.md`.
2. **Deferred cross-cutting roadmap:** Middleware, streaming, modality runtimes, conversation convergence, and attachment persistence were scoped in `implement-deferred-runtime-phases` but not landed as a single program.
3. **Packaging and docs:** README CI badges reference workflows absent from this repo; `composer.json` claims “full” toolkit and “observability” beyond Laravel events + redaction; vector default driver has no second production implementation in-tree.

This design subsumes the sequencing rationale already documented in `implement-deferred-runtime-phases/design.md` and adds decisions for **package hardening** and **test gaps**.

## Goals / Non-Goals

**Goals**

- Close every requirement in the six carried capability specs under `specs/`.
- Add explicit **package-hardening** requirements (docs, composer messaging, vector strategy, fake/test limitations).
- Preserve additive-first rollout: new APIs and config defaults keep current behavior until callers opt in (except where spec explicitly requires new primary path for evaluation).

**Non-Goals**

- Implementing provider-specific streaming for every vendor in v1 of streaming (support matrix is iterative).
- Building a hosted observability product (metrics backends); align **wording** with shipped behavior instead.

## Decisions

### D1 — Phased implementation order (unchanged from deferred change)

1. **Structured evaluation migration** — uses existing runtime primitives; removes primary reliance on text JSON heuristics.
2. **Runtime middleware** — wraps stable `execute()` path before streaming splits the call graph.
3. **Streaming** — builds on middleware hooks for observation and cancellation.
4. **Modality runtimes** — orthogonal contracts; transcription can replace ad-hoc “prompt-only” audio paths internally.
5. **Conversation store convergence** — required before default attachment replay to avoid double migration.
6. **Attachment persistence** — depends on converged message model and policies.
7. **Package hardening** — can proceed in parallel after phase 1 for README/composer fixes; vector driver work can land after modality/embeddings direction is clear.

### D2 — Structured evaluation: schema source of truth

- **Choice:** Specialist builds `ExecutionRequest` with a **non-null schema** (closure, `ObjectSchema`, or `HasStructuredOutput` class-string) derived from the registered prompt or a small package-owned schema definition for the fixed evaluation JSON shape.
- **Primary success path:** Populate blueprint output from `ExecutionResult->structuredOutput` when non-null and schema-valid.
- **Fallback:** When `structuredOutput` is null (provider limitation, SDK fake normalization, or refusal), invoke **bounded** `StructuredEvaluationOutputNormalizer` on `output` and emit a **redacted** package event or metadata flag indicating `fallback_path = text_normalization` (exact shape to be finalized in implementation; must not leak prompts).

### D3 — Middleware: one pipeline, many entry points

- **Choice:** Introduce `RuntimeMiddleware` (or equivalent) contract and a **single ordered stack** resolved from container/config.
- **Application points:** `SdkAiRuntime::execute` (inner core), `CompiledBlueprintRunner` / `BlueprintRunner`, and any orchestration leaf path that invokes `AiRuntime` (ensure orchestrator does not bypass the stack).
- **Ordering:** Document “before execute” vs “after execute” phases; use Laravel-style `terminate` pattern only if it matches PHPStan and test ergonomics.

### D4 — Streaming: opt-in execution mode

- **Choice:** Add `executeStream(...)` (name TBD) or `ExecutionRequest` flag `stream: bool` default false. Emit immutable **chunk / complete / fail** value objects; optionally forward to `Illuminate\Contracts\Events\Dispatcher` using **new** package event classes.
- **Broadcast:** Optional config `streaming.broadcast_channel` (or callback) — default off.

### D5 — Modality runtimes: sibling contracts to `AiRuntime`

- **Choice:** New interfaces under `Contracts/Core/` (e.g. `TranscriptionRuntime`, `EmbeddingsRuntime`, `ImageGenerationRuntime`, `RerankingRuntime`) with thin SDK adapters. Keep `AiRuntime` text-focused to avoid exploding one god interface.
- **Blueprint impact:** `AudioToTextToEvaluationTranscriptionAgent` SHOULD migrate to `TranscriptionRuntime` once the adapter exists; until then, document interim behavior in tasks.

### D6 — Conversation convergence: adapter first

- **Choice:** Introduce an **adapter** implementing or bridging to Laravel AI’s conversation contracts (exact interface names finalized against installed `laravel/ai` version during implementation). Package `ConversationStore` remains the persistence authority until a deprecation window elapses.
- **Legacy read:** Fixture-based tests proving old DB rows still load.

### D7 — Attachment persistence: policy-gated replay

- **Choice:** Store **references** (not necessarily raw bytes) on `ConversationMessage` or a side table keyed by message ID. `RuntimeConversationMemoryBridge` gains replay policy: max age, role allowlist, authorization hook, and observability when an attachment is skipped.

### D8 — Package hardening: vectors

- **Choice A (preferred):** Ship a **second** `VectorStoreInterface` implementation (e.g. SDK-backed retrieval bridge or DB-backed stub) selectable via config, **or**
- **Choice B:** Document that only `in_memory` ships in-box and rename config to avoid implying unsupported drivers.

Pick A if a minimal SDK-backed path is feasible within the same `laravel/ai` constraint; otherwise B for the first merge and track A as a follow-up task.

### D9 — Testability: structured output under fakes

- **Choice:** Add integration coverage that asserts non-null `structuredOutput` using either (1) a narrow test double that returns `StructuredAgentResponse` without normalization, or (2) conditional skip with documented reason — **preference is (1)** so spec §“structured call populates structuredOutput” is testable in CI.

## Risks / Trade-offs

- **Streaming + middleware interaction** — risk of duplicate events; mitigate with single dispatch owner and tests for ordering.
- **Conversation migration** — risk of data loss; mitigate with read-bridge + dual-write phase if needed (tasks split if dual-write is required).
- **Attachment security** — replay could exfiltrate; mitigate with explicit authorization and default-deny replay for non-user roles.

## Migration Plan

- Land phases with **config defaults preserving old behavior** until explicitly enabled (except structured evaluation primary path, which is a behavior change mitigated by fallback + telemetry).
- Each phase updates `UPGRADE.md` with observable deltas.
- After structured migration stabilizes, deprecate redundant normalizer code paths in a later minor if coverage proves sufficient.

## Open Questions

- Should streaming be exposed on `AgentKit` facade immediately or only via injected runtime?
- Minimum Laravel AI version bump required for conversation contract alignment?
- For vectors: is an SDK-backed “internal retrieval” adapter sufficient for v1, or do apps require pluggable external index IDs?
