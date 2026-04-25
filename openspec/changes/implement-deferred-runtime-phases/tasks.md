## 1. Structured evaluation migration (debt-first)
- [ ] 1.1 Add schema-first execution path in `TextToStructuredEvaluation` flow using `ExecutionResult.structuredOutput`.
- [ ] 1.2 Keep bounded fallback behavior with explicit observability for missing structured payloads.
- [ ] 1.3 Add/update tests covering schema-success and fallback-policy branches.

## 2. Runtime middleware adoption
- [ ] 2.1 Introduce runtime middleware pipeline contract and default wiring for runtime dispatch.
- [ ] 2.2 Apply middleware consistently across direct runtime, blueprint runner, and orchestrator surfaces.
- [ ] 2.3 Add deterministic middleware ordering and failure propagation tests.

## 3. Streaming runtime support
- [ ] 3.1 Add streamable runtime execution API and package-level stream event model (chunk/completion/failure).
- [ ] 3.2 Integrate stream event forwarding through broadcast/event surfaces used by consumers.
- [ ] 3.3 Add tests for event ordering, terminal completion, and terminal failure behavior.

## 4. Modality runtimes (transcription, embeddings, image generation, reranking)
- [ ] 4.1 Define modality request/result contracts and runtime interfaces.
- [ ] 4.2 Implement baseline SDK-backed adapters for each modality.
- [ ] 4.3 Add modality-specific tests including batch ordering guarantees for embeddings.

## 5. Conversation-store convergence
- [ ] 5.1 Align package conversation abstractions with Laravel AI conversation contracts.
- [ ] 5.2 Implement backward-compatible read bridge for legacy conversation records.
- [ ] 5.3 Add migration verification tests using legacy fixtures and aligned contract assertions.

## 6. Attachment persistence across turns
- [ ] 6.1 Add attachment persistence model tied to conversation message history.
- [ ] 6.2 Implement replay policy enforcement (retention, expiry, authorization) before runtime dispatch.
- [ ] 6.3 Add tests for replay inclusion/exclusion and observability of policy-driven exclusions.

## 7. Documentation and rollout
- [ ] 7.1 Update `UPGRADE.md` with any externally observable behavior/API changes per phase.
- [ ] 7.2 Update README/docs for new runtime entry points (streaming/modality/middleware as shipped).
- [ ] 7.3 Publish phased release notes and recommended implementation order rationale.