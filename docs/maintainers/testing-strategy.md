# Testing strategy

This maintainer reference defines how repository tests preserve the boundary between Agent Kit package semantics and Laravel AI SDK bridge behavior.

## Test layers

| Layer | Use for | Preferred fake layer |
|-------|---------|----------------------|
| Unit | pure DTOs, validators, redactors, prompt rendering, retry calculations | none |
| Package integration | blueprints, orchestration, memory, tools, provider policy, telemetry | package fakes |
| Runtime alignment | SDK bridge mapping and SDK event normalization | Laravel AI SDK fakes or narrow bridge fakes |
| Contract/conformance | memory/vector/provider capability behavior across implementations | package fakes or adapter fakes |

## Core rule

Public package behavior should be asserted in package terms:

- package-owned DTOs
- package-owned contracts
- package-owned exceptions
- package-owned events
- package-owned traces
- package-owned redaction behavior

Do not turn provider-native response classes or SDK internal payloads into public package expectations.

## Determinism requirements

- no live provider calls
- no provider credentials required
- no network access
- stable fake responses
- controlled time for retry, backoff, timeout, and retention tests
- no secret-bearing environment assumptions

## Fake selection

Use package fakes when the test is about Agent Kit semantics. Use Laravel AI SDK fakes only when the test is specifically about the bridge to Laravel AI SDK.

## Review checklist

Before merging a test, verify:

1. The test layer is correct.
2. The smallest appropriate fake layer is used.
3. Assertions are package-owned unless this is a bridge test.
4. The test is deterministic and network-free.
5. The test would still make sense if internal SDK wiring changed but the package public contract did not.
