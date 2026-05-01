## Context

`evolve-text-execution-surface` delivered a unified request/response text surface and intentionally deferred six cross-cutting concerns: streaming, modality runtimes, runtime middleware, conversation-store convergence, attachment persistence across turns, and structured evaluation migration. These concerns are interdependent (especially conversation + attachment lifecycle) and should be implemented in a staged order rather than as isolated one-off patches.

The package now has core primitives (`ExecutionRequest`, `ExecutionResult.structuredOutput`, blueprint runner flow) needed to land this follow-up with less API churn. The main constraint is avoiding a second broad breaking wave by sequencing additive changes first and converging storage semantics once runtime behaviors are stable.

## Goals / Non-Goals

**Goals:**
- Define an implementation roadmap that covers all deferred items with explicit sequencing constraints.
- Keep the first implementation slices additive where possible (streaming, structured evaluation migration, middleware).
- Isolate migration-risk work (conversation-store convergence + attachment persistence) behind explicit compatibility steps.
- Keep modality runtimes contract-driven and independent from text-runtime behavior.

**Non-Goals:**
- Implementing all deferred items in one release.
- Finalizing provider-specific behavior for every supported model vendor in this proposal stage.
- Redesigning unrelated orchestration features outside deferred-item scope.

## Decisions

### Decision 1: Use a phased execution order with low-risk early wins
- **Choice:** Sequence implementation as: (1) structured evaluation migration, (2) runtime middleware, (3) streaming, (4) modality runtimes, (5) conversation-store convergence, (6) attachment persistence.
- **Rationale:** This order maximizes value while minimizing cross-cutting migration risk. Early phases consume already-landed primitives and reduce technical debt before introducing persistence/contract migrations.
- **Alternative considered:** Implement by original roadmap numbering from prior proposal (phases 3→7). Rejected because it mixes independent and dependent items and does not prioritize debt-reduction opportunities.

### Decision 2: Keep streaming and modality contracts separate
- **Choice:** Define streaming as text-runtime delivery semantics and modality runtimes as distinct contracts.
- **Rationale:** Streaming is transport/interaction behavior; modality runtimes are domain-specific execution APIs. Separating them avoids forcing all modalities into stream semantics before requirements are stable.
- **Alternative considered:** One unified "multi-modal streaming runtime" contract. Rejected due to premature coupling and higher implementation complexity.

### Decision 3: Converge conversation storage before attachment replay defaulting
- **Choice:** Attachment persistence depends on converged conversation abstractions and should not become default behavior until convergence compatibility paths exist.
- **Rationale:** The current memory bridge is string-oriented; replaying attachments without contract convergence risks duplicate migration efforts and undefined retrieval semantics.
- **Alternative considered:** Add attachment replay immediately with side-channel storage. Rejected because it introduces temporary data models that must later be migrated.

### Decision 4: Structured evaluation migration should target normalizer retirement
- **Choice:** Migrate `TextToStructuredEvaluation` to direct `structuredOutput` consumption and treat existing text normalizer as fallback-only compatibility path.
- **Rationale:** This removes brittle JSON extraction heuristics and aligns behavior with the newly introduced runtime surface.
- **Alternative considered:** Keep current normalizer-first path and add schema as optional optimization. Rejected because it preserves core fragility and delays simplification.

## Risks / Trade-offs

- **[Risk] Streaming API mismatch across providers** → **Mitigation:** normalize provider stream events into package-level chunk/completion/failure event model with conformance tests.
- **[Risk] Modality surface expansion increases maintenance cost** → **Mitigation:** enforce strict contract boundaries and shared runtime abstractions for retries/observability.
- **[Risk] Middleware ordering bugs across execution surfaces** → **Mitigation:** add deterministic order tests for direct runtime, blueprint, and orchestrator entry points.
- **[Risk] Conversation migration may break legacy records** → **Mitigation:** provide compatibility bridge and migration verification tests against legacy fixture records.
- **[Risk] Attachment replay introduces security/privacy regressions** → **Mitigation:** enforce explicit retention/authorization policy and observability events for excluded attachments.

## Migration Plan

1. Land structured evaluation migration with fallback telemetry and no storage changes.
2. Introduce runtime middleware pipeline in additive mode; keep existing behavior default.
3. Add streaming execution and event forwarding with opt-in consumption paths.
4. Introduce modality runtime contracts and baseline adapters (transcription, embeddings, image generation, reranking).
5. Implement conversation-store convergence with backward-compatible read bridge and migration tooling/tests.
6. Enable attachment persistence/replay on converged conversation abstractions; gate rollout with retention/authorization policies.
7. Document upgrade paths in `UPGRADE.md` for any externally observable contract or behavior changes.

Rollback strategy:
- Each phase is independently releasable; disable new entry points by configuration/feature gates if regressions occur.
- Preserve legacy conversation read path until convergence validation passes in production-like environments.

## Open Questions

- Should streaming become default for any existing orchestration path, or remain explicit opt-in only?
- What is the minimum provider coverage required to mark modality runtimes as production-ready?
- Should attachment replay policy default to "current turn only" or "all retained turns" after convergence?
- At what milestone can `StructuredEvaluationOutputNormalizer` be fully removed instead of fallback-only?