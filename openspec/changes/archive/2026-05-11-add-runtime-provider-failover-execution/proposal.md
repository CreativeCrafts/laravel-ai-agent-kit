## Why

Agent Kit exposes provider selection, failover order, provider failover events, and circuit-breaker-aware failover policy, but the runtime execution path currently performs a single SDK prompt/stream attempt against the request provider/model or SDK defaults. That creates a reliability gap and makes the configured failover surface look stronger than the actual runtime behavior.

Laravel AI SDK also treats provider failover as a first-class provider capability. Agent Kit should make package-owned provider policy authoritative for direct runtime, blueprint, orchestration, and streaming entry points.

## What Changes

- Resolve an effective provider/model before runtime execution when a request does not explicitly specify one.
- Execute eligible prompt failures through configured provider failover until success or exhaustion.
- Define and implement streaming failover behavior explicitly.
- Integrate failover attempts with existing provider failover events and circuit breaker state.
- Preserve schema, tools, provider tools, attachments, memory projection, budgets, and generation options across attempts.
- Add tests for success-after-failover, exhausted failover, circuit-breaker skips, structured-output failover, and streaming failover behavior.

## Capabilities

### New Capabilities
- `runtime-provider-failover`: Package-owned runtime provider resolution and failover execution semantics.

### Modified Capabilities
- `runtime-execution`: Runtime requests use effective provider policy consistently across direct runtime, blueprints, and orchestration.
- `runtime-streaming`: Streaming provider failure behavior is explicit and test-covered.
- `provider-selection`: Provider selectors move from advisory surfaces to runtime execution inputs.

## Impact

- **Code areas:** `src/Core/Runtime/SdkAiRuntime.php`, provider selectors, circuit breaker manager, runtime events, blueprint runner, orchestration path, tests, and docs.
- **Public API:** No required constructor changes for `ExecutionRequest`; behavior changes when provider/model are omitted or when provider calls fail.
- **Migration risk:** Medium. Applications that depended on raw SDK default provider fallback may now use Agent Kit default provider policy unless they pass explicit provider/model.
- **Operational risk:** Lower provider outage impact, but higher chance of multiple provider calls per user request when failover is configured.
