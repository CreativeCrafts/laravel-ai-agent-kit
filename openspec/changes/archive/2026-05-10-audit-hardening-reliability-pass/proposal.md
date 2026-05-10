## Why

A code audit of the current `main` branch found several security, correctness, and reliability hardening opportunities in runtime streaming, tool input validation, vector persistence, and queued pipeline dispatch.

The current posture is generally strong: custom tools are default-deny, provider tools are separately authorized, runtime budget enforcement is fail-closed for configured cost ceilings, vector namespaces enforce embedding dimensions, and telemetry is designed around redacted package events.

The remaining gaps are targeted and should be addressed before the next release because they affect package behavior at model/provider and infrastructure boundaries:

- streaming provider failures thrown before stream iteration can bypass the package's normalized `StreamFailure` path;
- custom tool schemas can be materialized recursively for providers, but package-side input validation is currently shallow;
- database vector upserts can race under concurrent writers because they use `exists()` followed by `insert()`/`update()`;
- queued pipeline payload size checks are debug-only, even though production queues can fail on oversized serialized jobs.

## What Changes

- Normalize synchronous stream-creation failures into `StreamFailure` results and redacted stream-failure telemetry.
- Align custom tool input validation with the schema surface exposed to providers by adding recursive validation for nested objects, arrays, enums, nullable values, and `additionalProperties` rules.
- Make database vector upsert behavior atomic for existing/new rows while preserving namespace embedding-dimension guards.
- Add an explicit production-capable queued pipeline payload guard while keeping the current debug guard behavior.
- Add regression coverage for all four areas.
- Update developer/production docs where behavior or configuration changes are user-facing.

## Capabilities

### New Capabilities

- `audit-hardening-reliability`: Security, correctness, and reliability hardening for runtime streaming, custom tools, vector persistence, and queued pipeline dispatch.

### Modified Capabilities

- Runtime streaming failure normalization.
- Custom tool schema validation and execution safety.
- Database vector store persistence semantics.
- Queued pipeline dispatch safeguards.

## Impact

- **Runtime:** `SdkAiRuntime::executeStream()` failure behavior becomes consistent whether the SDK throws during stream creation or iteration.
- **Tools:** custom tools receive stricter package-side input validation before execution.
- **Vectors:** concurrent database vector indexing becomes less prone to duplicate-key failures.
- **Queues:** applications can opt into serialized queued job size enforcement outside debug mode.
- **Tests:** add targeted regression tests for stream failure normalization, recursive tool validation, database vector upsert, and queue payload guard behavior.
- **Docs:** update queue/tool/runtime/vector documentation if public configuration or behavior changes.
