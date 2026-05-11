## Context

Agent Kit currently has provider definitions, default provider selection, failover ordering, failover observability events, and optional circuit-breaker filtering. The SDK runtime, however, builds one Laravel AI agent and calls the SDK once for prompt or streaming execution. The package needs a runtime-level attempt loop that uses Agent Kit provider policy as the source of truth.

## Goals

- Resolve effective provider/model consistently before execution.
- Retry eligible provider failures through configured failover order.
- Preserve all request semantics across attempts.
- Emit existing failover and runtime failure telemetry with redacted context.
- Keep explicit request provider/model behavior deterministic.
- Define streaming failover behavior before implementation.

## Non-Goals

- Replacing Laravel AI SDK provider internals.
- Adding new provider drivers.
- Changing public `ExecutionRequest` constructor parameters unless implementation proves unavoidable.
- Retrying validation, authorization, schema, memory, or local configuration failures as provider failover.

## Design

### Effective provider resolution

Runtime execution should derive an `EffectiveRuntimeProvider` value before building the SDK agent:

- If `ExecutionRequest::$provider` is non-null, use that provider as the first attempt.
- If `ExecutionRequest::$provider` is null, resolve the default provider through `ProviderSelector` or the existing agent profile selector path.
- Resolve model from the request when provided; otherwise use provider profile/options if available; otherwise leave null for SDK default behavior.

The resolved provider/model should be recorded in result metadata and failure events.

### Attempt loop

For prompt execution:

1. Project memory once before provider attempts.
2. Materialize custom and provider tools once before provider attempts.
3. Build and execute an SDK agent per provider attempt using the same request payload and attempt-specific provider/model.
4. On success, reconcile memory once with the successful response.
5. On eligible provider failure, ask `FailoverProviderSelector::nextAfter()` for the next provider.
6. Stop on success or failover exhaustion.

Non-provider failures should not retry through failover. Examples: invalid schema, tool authorization denied, memory projection failure, budget preflight failure, request validation failure.

### Circuit breaker integration

- Provider failures that are eligible for failover should record a failure against `providers.<name>`.
- Successful attempts should record success against the winning provider.
- `FailoverProviderSelector` already skips open breakers when configured; the runtime should use that behavior rather than duplicating filtering.

### Streaming behavior

The change must define and test one of these policies before code implementation:

- **Creation-only failover:** Fail over only when stream creation fails before any chunk is emitted.
- **No mid-stream failover:** Once a chunk is emitted, any provider error becomes a terminal `StreamFailure`; do not replay partial prompts on another provider.

Recommended default: creation-only failover, no mid-stream failover.

### Telemetry

- Retain `RuntimeExecutionFailed` and `RuntimeStreamFailed` for final terminal failures.
- Use `ProviderFailoverResolved`, `ProviderFailoverExhausted`, and `ProviderSkippedByCircuitBreaker` for failover decision visibility.
- Add attempt metadata where useful: attempt count, attempted providers, final provider, failover exhausted flag.

## Risks

- Duplicate side effects if providers execute tools before failing. Mitigation: failover should only retry provider invocation failures where the SDK returns/throws before package tool side effects are committed, or document conservative retry eligibility.
- Streaming retry can duplicate partial output if performed after chunks. Mitigation: no mid-stream failover.
- Provider/model option mapping may differ across providers. Mitigation: use provider profiles/options and keep request explicit provider/model highest precedence.

## Open Questions

- Should explicit request provider opt out of failover, or should failover continue from that provider's position in `failover_order`? Proposed: explicit provider is the first attempt and failover continues only if the provider is present in failover order.
- Should failover be disabled when `failover_order` has only one provider? Proposed: yes, naturally exhausted.
