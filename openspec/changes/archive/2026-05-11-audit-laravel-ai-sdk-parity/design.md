## Context

Agent Kit deliberately does not expose Laravel AI SDK objects directly as its public API for every capability. It provides package-owned abstractions where workflow, memory, tooling policy, provider policy, or telemetry are important. Some SDK capabilities are intentionally left for direct SDK usage. This design requires an explicit parity governance process so drift does not become accidental.

## Goals

- Establish a repeatable SDK parity audit for `laravel/ai` upgrades.
- Classify every SDK capability as wrapped, direct-SDK, deferred, or out of scope.
- Verify public docs describe the intended developer path for each capability category.
- Verify package fakes cover all package-owned public surfaces.
- Verify package event normalization covers operationally important SDK events or documents direct SDK event usage.
- Produce actionable follow-up proposals for real wrapper gaps.

## Non-Goals

- Implementing every wrapper found during the audit.
- Mirroring Laravel AI SDK APIs one-for-one.
- Replacing current Agent Kit package-owned contracts with SDK contracts.
- Changing dependency constraints unless the audit finds a concrete compatibility issue.

## Audit Dimensions

### Runtime and agents

- Anonymous/text agent execution.
- Structured output.
- Streaming and broadcasting.
- Generation options.
- Middleware.
- Conversation context.
- Provider selection and failover.

### Modalities

- Embeddings.
- Embedding querying/caching.
- Image generation.
- Audio generation.
- Transcription.
- Reranking.

### Retrieval and files

- Files.
- Stores.
- File search/provider retrieval tools.
- SDK vector stores.
- Agent Kit application-owned vectors.

### Tools

- Custom tools.
- Provider-native tools.
- Tool authorization and schema validation.
- Tool testing fakes.

### Async and jobs

- SDK jobs for agents, broadcasting, embeddings, images, audio, transcription, and any new jobs.
- Package queued pipeline recommendation and limitations.

### Events and observability

- SDK prompt/tool/stream/modality/file/store events.
- Package redacted event normalization.
- Direct SDK event escape hatch documentation.

### Testing and fakes

- Package fakes for runtime, modalities, vectors, tools, providers, queues, memory, and files/stores where applicable.
- Parity with public Agent Kit manager/facade methods.

## Deliverables

1. Updated `docs/maintainers/sdk-capability-matrix.md`.
2. Updated `docs/maintainers/sdk-async-inventory.md`.
3. New or updated maintainer event/provider-tool inventory if needed.
4. Public docs section explaining Agent Kit vs direct Laravel AI SDK usage.
5. Test coverage for fake parity gaps that are fixed during the sweep.
6. Follow-up OpenSpec proposals for implementation gaps that are too large for this audit change.

## Classification Rules

- **Package-owned:** Agent Kit should wrap this SDK capability because it needs package policy, memory, telemetry, workflows, authorization, or testing fakes.
- **Direct-SDK:** Developers should use Laravel AI SDK directly because the capability is thin, SDK-specific, or outside Agent Kit's workflow value proposition.
- **Deferred:** Useful package wrapper but not required for current release quality.
- **Out of scope:** SDK capability does not fit Agent Kit responsibilities.

## Risks

- Scope creep into implementing wrappers instead of documenting decisions. Mitigation: this change produces inventories and follow-ups; implementation belongs in later proposals.
- SDK docs and source can differ. Mitigation: audit both docs and installed source where available.
- Over-wrapping can reduce SDK ergonomics. Mitigation: direct-SDK classification is valid and should be documented.
