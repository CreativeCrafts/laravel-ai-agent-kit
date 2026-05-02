> **Task status:** The checklist below is marked **complete** to reflect that the work was delivered under the canonical program **`2026-05-01-close-agent-kit-gaps`** (this folder was a duplicate tracker). See `openspec/changes/archive/2026-05-01-close-agent-kit-gaps/tasks.md` for the authoritative completed list.

## 1. Structured evaluation migration (debt-first)
- [x] 1.1 Add schema-first execution path in `TextToStructuredEvaluation` flow using `ExecutionResult.structuredOutput`.
- [x] 1.2 Keep bounded fallback behavior with explicit observability for missing structured payloads.
- [x] 1.3 Add/update tests covering schema-success and fallback-policy branches.

## 2. Runtime middleware adoption
- [x] 2.1 Introduce runtime middleware pipeline contract and default wiring for runtime dispatch.
- [x] 2.2 Apply middleware consistently across direct runtime, blueprint runner, and orchestrator surfaces.
- [x] 2.3 Add deterministic middleware ordering and failure propagation tests.

## 3. Streaming runtime support
- [x] 3.1 Add streamable runtime execution API and package-level stream event model (chunk/completion/failure).
- [x] 3.2 Integrate stream event forwarding through broadcast/event surfaces used by consumers.
- [x] 3.3 Add tests for event ordering, terminal completion, and terminal failure behavior.

## 4. Modality runtimes (transcription, embeddings, image generation, reranking)
- [x] 4.1 Define modality request/result contracts and runtime interfaces.
- [x] 4.2 Implement baseline SDK-backed adapters for each modality.
- [x] 4.3 Add modality-specific tests including batch ordering guarantees for embeddings.

## 5. Conversation-store convergence
- [x] 5.1 Align package conversation abstractions with Laravel AI conversation contracts.
- [x] 5.2 Implement backward-compatible read bridge for legacy conversation records.
- [x] 5.3 Add migration verification tests using legacy fixtures and aligned contract assertions.

## 6. Attachment persistence across turns
- [x] 6.1 Add attachment persistence model tied to conversation message history.
- [x] 6.2 Implement replay policy enforcement (retention, expiry, authorization) before runtime dispatch.
- [x] 6.3 Add tests for replay inclusion/exclusion and observability of policy-driven exclusions.

## 7. Documentation and rollout
- [x] 7.1 Update `UPGRADE.md` with any externally observable behavior/API changes per phase.
- [x] 7.2 Update README/docs for new runtime entry points (streaming/modality/middleware as shipped).
- [x] 7.3 Publish phased release notes and recommended implementation order rationale.