# Failure Normalization and Telemetry Semantics

## Status

This document is the implementation artifact for:

- `P1Y-I7 Normalize provider failures, refusals, and telemetry into package semantics`

It documents the package-owned failure and telemetry behavior that callers and maintainers should rely on after the `P1Y-I7` hardening work.

---

## Purpose

Laravel AI Agent Kit is an SDK-backed package, but failure behavior and telemetry semantics are package-owned.

That means:

- raw provider-native failure payloads are **not** the stable package contract
- refusal behavior must be expressed in package terms where the package owns the workflow semantics
- telemetry must remain redacted by default
- failover and provider-profile transitions must stay observable without leaking raw provider payloads

This document records the normalized failure model that now applies across the relevant runtime, blueprint, orchestration, and provider-failover paths.

---

## Package-Owned Failure Categories

The package now uses a stable normalized failure-category vocabulary:

- `execution_failed`
- `provider_failure`
- `budget_exceeded`
- `refusal`
- `malformed_output`
- `invalid_output`
- `provider_profile_mismatch`
- `failover_policy_error`
- `logical_failure`

These categories are package-owned semantics.

They are not provider marketing terms and they are not raw SDK/provider exception taxonomies.

---

## Where Categories Apply

### Runtime failures

`SdkAiRuntime` now emits normalized runtime failure telemetry through package-owned semantics.

Examples:

- provider prompt-edge failures are categorized as `provider_failure`
- runtime bridge or generic runtime execution failures are categorized as `execution_failed`
- runtime budget failures are categorized as `budget_exceeded`

### Blueprint-owned refusal and malformed-output paths

Where the package owns the structured-output workflow semantics, refusal and malformed-output behavior are normalized into package exceptions rather than leaked as provider-native payloads.

Examples:

- refusal-style structured-output responses are categorized as `refusal`
- invalid JSON responses are categorized as `malformed_output`
- invalid package payload shape is categorized as `invalid_output`

### Orchestration failures

`OrchestrationFailed` now carries a normalized failure category.

Examples:

- thrown package exceptions preserve their normalized category
- terminal agent fail results without a thrown exception are categorized as `logical_failure`

### Provider failover policy

Provider-profile and failover-policy failures remain package-owned.

Examples:

- incompatible agent provider profiles are categorized as `provider_profile_mismatch`
- failover-order / disabled-provider policy errors are categorized as `failover_policy_error`

---

## Package-Owned Failure Events

### `RuntimeExecutionFailed`

This event is emitted when runtime execution fails before completion.

It intentionally exposes package-safe context:

- `runId`
- `provider`
- `model`
- requested tool names
- redacted input key list
- redacted metadata key list
- package conversation id when available
- conversation bridging flags
- projected message count
- normalized `failureCategory`
- package exception class
- redacted exception message

It intentionally does **not** expose:

- raw prompt text
- raw input payload values
- raw metadata values
- raw provider-native failure payloads

### `OrchestrationFailed`

This event continues to expose redacted orchestration failure context and now also carries `failureCategory`.

### `ProviderFailoverResolved`

This event continues to show current provider, next provider, and ordered provider lineage when a failover transition is resolved.

### `ProviderFailoverExhausted`

This event is emitted when failover resolution is attempted and no later eligible provider remains.

This makes failover exhaustion observable directly instead of requiring callers to infer exhaustion from a `null` next provider alone.

---

## Redaction Rules

Failure and telemetry behavior remains redacted by default.

The package intentionally favors:

- key-level input and metadata visibility
- redacted exception messages
- package-owned IDs and counts
- provider-profile lineage

over:

- raw prompt bodies
- raw user input
- raw provider-native payloads
- secret-bearing metadata values

This is a hard package safety rule, not optional guidance.

---

## Upgrade Notes

`P1Y-I7` changes how failures should be interpreted operationally:

### Before

Callers could still observe package exceptions and telemetry, but failure classification was fragmented and less reusable across runtime, blueprint, and orchestration paths.

### After

Callers and maintainers should treat:

- normalized failure categories,
- redacted package-owned failure events,
- and explicit failover exhaustion visibility

as the authoritative package semantics.

### Migration guidance for maintainers

If you add new package-owned workflows or exception types:

1. classify failures in package terms
2. preserve redacted telemetry by default
3. avoid raw provider payload leakage
4. make failure semantics reusable through the neutral failure-category carrier pattern
5. add deterministic regression coverage for the new failure path

---

## Testing Expectations

The normalized failure model is now expected to be regression-covered with deterministic tests for:

- generic runtime execution failure telemetry
- provider prompt-edge failure telemetry
- budget-exceeded runtime telemetry
- failover exhaustion visibility
- refusal-category orchestration failure telemetry
- category resolution through the neutral package-owned failure-category carrier

These tests must remain package-integration or conformance-style tests with no live provider access.

---

## Decision Rules Going Forward

When implementing future execution paths:

1. Do not expose raw provider-native failure payloads as the package contract.
2. Normalize package-owned refusal semantics where the package owns the workflow shape.
3. Emit redacted package-owned telemetry.
4. Keep provider-profile transitions observable.
5. Reuse the normalized failure-category model rather than inventing workflow-local taxonomies.

---

## Summary

`P1Y-I7` makes failure handling part of the replacement-quality contract.

Success-path parity is not enough.

The package now defines stable, redacted, package-owned semantics for:

- provider failures
- runtime failures
- budget failures
- refusals
- malformed output
- provider-profile mismatches
- failover exhaustion
- orchestration logical failures
