## Why

Agent Kit depends on `laravel/ai` and intentionally wraps selected SDK capabilities behind package-owned contracts, policy, telemetry, memory, and workflow surfaces. The package already maintains an SDK capability matrix and async inventory, but SDK parity can drift as Laravel AI adds or changes capabilities.

A dedicated SDK parity sweep should compare the currently supported Laravel AI SDK version range against Agent Kit's public surfaces, fakes, event normalization, documentation, and intentional escape hatches. The goal is not to blindly mirror the SDK; it is to make every gap explicit as either supported, intentionally direct-SDK, deferred, or out of scope.

## What Changes

- Audit `laravel/ai ^0.6` SDK surfaces against Agent Kit runtime, modality, files/stores, provider tools, queueing, events, testing fakes, and docs.
- Update maintainer inventories for SDK capabilities, async jobs, events, provider tools, modalities, files/stores, vector stores, and middleware.
- Identify missing Agent Kit wrappers, fakes, docs, or event normalizers.
- Classify each gap as package-owned, direct-SDK escape hatch, deferred, or out of scope.
- Add tests or docs where current behavior is intentional but undocumented.
- Create follow-up implementation changes for any high-value wrapper gaps discovered.

## Capabilities

### New Capabilities
- `sdk-parity-governance`: Maintainer process and acceptance criteria for keeping Agent Kit aligned with supported Laravel AI SDK surfaces.

### Modified Capabilities
- `developer-documentation`: Public docs explain when to use Agent Kit surfaces versus direct Laravel AI SDK surfaces.
- `maintainer-documentation`: SDK matrix and async inventory become required update points for SDK upgrades.
- `testing-fakes`: Package fake parity is audited against current public package surfaces and intentional SDK escape hatches.

## Impact

- **Code areas:** `docs/maintainers/**`, `docs/**`, package fakes, event normalizer, modality wrappers, provider tool wrappers, tests, and changelog.
- **Public API:** No immediate API change required by this proposal; follow-up changes may add wrappers or fakes.
- **Migration risk:** Low for this audit/change-planning work.
- **Operational risk:** Lower long-term drift risk between Agent Kit and the underlying Laravel AI SDK.
