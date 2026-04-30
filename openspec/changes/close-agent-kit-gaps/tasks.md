## Phase 0 — Package hardening (parallel-friendly)

Documentation, messaging, and testability gaps that do not depend on runtime phases.

- [x] 0.1 Update README GitHub Actions badge URLs and any hard-coded workflow links to match files under `.github/workflows/` (or add missing workflows if product policy requires those names). Verify links on default branch.
- [x] 0.2 Revise `composer.json` description (and keywords if needed) so claims match shipped public API: replace overstated “full observability” language with accurate event/redaction semantics unless a new observability API is shipped in the same phase.
- [x] 0.3 Implement `package-hardening` vector requirement: either (A) add a documented second `VectorStoreInterface` driver and wire `ai-agent-kit.vector.default_driver`, or (B) narrow config/README to `in_memory` only and remove misleading driver placeholders.
- [x] 0.4 Add CI-verifiable structured-output assertion per `package-hardening` spec (test double or harness that preserves structured payload; document any remaining SDK fake limitations in test comments only if unavoidable).

## Phase 1 — Structured evaluation migration

Satisfies `specs/structured-evaluation-migration/spec.md` and unblocks normalizer retirement.

- [ ] 1.1 Define the evaluation JSON schema for `TextToStructuredEvaluation` as `ObjectSchema`, `HasStructuredOutput`, or closure; thread `schema` (and optional `generationOptions`) through `PromptExecutionMapper` / specialist agent `ExecutionRequest` construction.
- [ ] 1.2 Primary path: build specialist `AgentExecutionResult` / blueprint mapping from `ExecutionResult->structuredOutput` when present and valid; validate required keys before returning.
- [ ] 1.3 Fallback path: when `structuredOutput` is null or fails validation, run existing `StructuredEvaluationOutputNormalizer` on `output`; set explicit observability (`metadata` flag and/or new redacted package event) per spec.
- [ ] 1.4 Extend `tests/TextToStructuredEvaluationBlueprintTest.php` (or add focused tests) for: structured primary success, fallback when `structuredOutput` null, refusal/invalid still typed.
- [ ] 1.5 Evaluate `AudioToTextToEvaluationTranscriptionAgent`: document interim behavior or add schema/structured path if SDK supports structured transcript object in-kit.

## Phase 2 — Runtime middleware

Satisfies `specs/runtime-middleware/spec.md`.

- [ ] 2.1 Add middleware contract(s), registration (service provider + config for ordered class names or tagged middleware), and pipeline executor.
- [ ] 2.2 Integrate pipeline into `SdkAiRuntime::execute` inner dispatch.
- [ ] 2.3 Integrate same pipeline into `CompiledBlueprintRunner` / `BlueprintRunner` so blueprints cannot bypass middleware.
- [ ] 2.4 Ensure orchestration-invoked runtime calls use the same middleware stack (audit all `AiRuntime` resolution sites).
- [ ] 2.5 Pest tests: deterministic order, failure propagation, blueprint vs direct parity.

## Phase 3 — Streaming runtime

Satisfies `specs/runtime-streaming/spec.md`.

- [ ] 3.1 Add stream-oriented API on `AiRuntime` or parallel interface (per design D4); define chunk/complete/fail value objects.
- [ ] 3.2 Implement SDK-backed streaming for at least one mainstream text path; normalize provider events into package events.
- [ ] 3.3 Optional broadcast/event forwarding behind config; document channel naming and payload redaction rules.
- [ ] 3.4 Pest tests: chunk ordering, terminal completion, terminal failure stops further chunks, optional broadcast assertion using `Event::fake`.

## Phase 4 — Modality runtimes

Satisfies `specs/modality-runtimes/spec.md`.

- [x] 4.1 Add contracts + request/result DTOs for transcription, embeddings, image generation, reranking.
- [x] 4.2 Implement baseline SDK-backed adapters per modality (spike against installed `laravel/ai` to confirm entry points).
- [x] 4.3 Register adapters in service provider with config-driven selection where multiple backends exist.
- [x] 4.4 Pest tests: transcription happy path, embeddings batch order preservation, image/rerank smoke tests behind fakes if live SDK calls are not CI-safe.
- [x] 4.5 Refactor audio blueprint transcription stage to modality runtime when feasible (tie-back to 1.5).

## Phase 5 — Conversation store convergence

Satisfies `specs/conversation-store-convergence/spec.md`.

- [ ] 5.1 Inventory Laravel AI conversation interfaces for the supported `laravel/ai` version; define adapter mapping from package `Conversation` / messages to aligned types.
- [ ] 5.2 Implement read bridge for legacy persisted rows; add fixture SQL or factories for pre-migration records.
- [ ] 5.3 Pest tests: legacy load, round-trip save/load for new format, contract assertions agreed in spec.

## Phase 6 — Attachment persistence

Satisfies `specs/attachment-persistence/spec.md`; depends on Phase 5 foundations.

- [ ] 6.1 Extend persistence model (migration + encryption policy if storing sensitive references) to store attachment metadata per message.
- [ ] 6.2 Implement replay policy module (retention, expiry, authorization); integrate with `RuntimeConversationMemoryBridge` so replay is explicit and testable.
- [ ] 6.3 Observability events or metadata when attachments excluded by policy.
- [ ] 6.4 Pest tests: inclusion, exclusion, authorization deny, observability signal.

## Phase 7 — Documentation and rollout

Satisfies `specs/documentation-rollout/spec.md` and closes the program.

- [ ] 7.1 Update `UPGRADE.md` after each phase that affects consumers; final pass for consistency.
- [ ] 7.2 Update README for streaming, middleware, modality entry points, attachment replay, vector drivers, and corrected CI badges.
- [ ] 7.3 Update `CHANGELOG.md` with phased release notes and recommended adoption order (reference `design.md` sequencing).

## Archival note

When implementation is complete, archive this change per `openspec-archive-change` skill and consider whether `openspec/changes/implement-deferred-runtime-phases` should be archived or superseded with a pointer to this change.
