# PROVIDER_CAPABILITY_MATRIX.md

## Status

This document is the implementation artifact for:

- `P1Y-I4 Provider capability matrix + conformance suite`

It is an internal planning SSOT under `plan/`, not a marketing compatibility document.

Its purpose is to answer one concrete project question:

> Which configured provider profiles can the package currently treat as capable of satisfying the audited assistant-replacement targets, and how are those capability claims proven deterministically?

---

## Why this document exists

The package already supports provider declarations, provider-profile selection, failover, and agent capability matching.

That is necessary, but it is not sufficient.

A configured provider profile declaring capabilities in config is only a claim. This issue adds the missing package-owned layer that turns those claims into:

- an audited capability matrix, and
- a deterministic conformance suite that later parity tests can rely on.

This keeps capability truth package-owned and regression-proof.

---

## Source of Truth Basis

This document is grounded in:

- `plan/ASSISTANT_REPLACEMENT_SURFACE.md`
- `config/ai-agent-kit.php`
- `src/Core/Providers/ProviderDefinition.php`
- `src/Core/Providers/ConfiguredProviderRegistry.php`
- `src/Core/Providers/ConfiguredAgentProviderProfileSelector.php`
- `src/Core/Providers/AuditedProviderCapabilityMatrix.php`
- `src/Core/Providers/ProviderCapabilityConformanceSuite.php`

If these sources change, this document must be updated accordingly.

---

## Audited Capability Targets

The audited matrix currently defines these package-owned capability targets:

| Audited capability target       | Kind            | Declared provider capability requirements                                                   |
|---------------------------------|-----------------|---------------------------------------------------------------------------------------------|
| `text_generation`               | single profile  | `text_generation`                                                                           |
| `structured_output`             | single profile  | `text_generation`, `structured_output`                                                      |
| `audio_transcription`           | single profile  | `audio_transcription`                                                                       |
| `tool_capable_execution`        | single profile  | `text_generation`, `tool_execution`                                                         |
| `memory_aware_continuation`     | single profile  | `text_generation`, `memory_continuation`                                                    |
| `text_to_structured_evaluation` | single profile  | `text_generation`, `structured_output`                                                      |
| `audio_to_text_to_evaluation`   | staged workflow | `transcription => audio_transcription`, `evaluation => text_generation + structured_output` |

These are intentionally package-owned targets. They are not provider marketing terms and they are not thin wrappers over vendor SDK nomenclature.

---

## Matrix Semantics

### Single-profile targets

A single-profile target is satisfied only when one configured provider profile declares all required provider capabilities for that target.

Examples:

- A profile that declares only `structured_output` does **not** satisfy the audited `structured_output` target because the package treats meaningful structured generation as requiring both:
		- `text_generation`
		- `structured_output`

- A profile that declares `text_generation` and `tool_execution` satisfies the audited `tool_capable_execution` target.

### Staged workflow targets

A staged workflow target is satisfied only when each required stage is backed by a configured provider profile that declares the capabilities required for that stage.

Current staged target:

- `audio_to_text_to_evaluation`
		- `transcription` stage requires `audio_transcription`
		- `evaluation` stage requires `text_generation` and `structured_output`

This is intentionally staged so later parity issues can verify mixed-provider workflows without collapsing all stage requirements into one provider profile.

---

## Conformance Suite Semantics

The matrix alone is declaration-level truth.

The conformance suite adds behavior-level proof.

`ProviderCapabilityConformanceSuite` works in two phases:

1. **Declaration gate**
		- It checks the configured provider profile or staged profile set against the audited matrix.
		- If the declared provider capabilities do not satisfy the target, it fails explicitly before running any probe.

2. **Deterministic proof**
		- It runs a deterministic probe supplied by the caller.
		- The probe is expected to use fakes or deterministic package runtime behavior only.
		- If the probe fails, the conformance suite wraps that failure in a typed package exception.

This keeps later parity issues honest:

- they cannot accidentally test a provider profile against a target it never truly declared support for,
- and they cannot treat declarations alone as proof of behavior.

---

## Intended Usage

### Current use

This issue introduces the package-owned foundation:

- `AuditedProviderCapabilityMatrix`
- `ProviderCapabilityConformanceSuite`

### Later use

Later issues should rely on this foundation:

- `P1Y-I5` for cross-provider `TextToStructuredEvaluation` parity
- `P1Y-I6` for mixed-provider `AudioToTextToEvaluation` stage parity
- `P1Y-I8` for provider-profile presets and examples
- `P1Y-I10` for release-readiness gating

Those issues should not invent separate capability truth tables.

---

## P1Y-I6 Stage-Parity Requirements

`P1Y-I6` proves the package-owned staged workflow expectations for `audio_to_text_to_evaluation`.

That parity layer must remain grounded in the audited matrix rather than ad hoc provider assumptions.

### What counts as a valid staged provider mix

A staged combination is valid only when:

- the selected `transcription` profile declares `audio_transcription`, and
- the selected `evaluation` profile declares both `text_generation` and `structured_output`.

Driver branding does not matter here. The package truth is capability-based.

### What parity coverage must prove

The mixed-provider audio parity suite must prove all of the following:

1. Mixed-provider transcription and evaluation stages can use different compatible provider profiles.
2. The final `AudioToTextToEvaluation` result remains stable in package-owned terms regardless of provider mix.
3. Stage-local capability mismatches fail explicitly through typed package exceptions.
4. Execution traces preserve provider-profile lineage across the transcription and evaluation stage boundaries.
5. The tests remain deterministic and network-free, using package fakes or deterministic runtime behavior only.

### What parity coverage must not do

The staged parity suite must not:

- rely on live providers,
- treat provider-native payloads as public package truth,
- change the public result DTO by provider,
- or bypass the audited matrix when deciding which provider combinations are valid.

### Current proven staged baseline

The staged parity baseline is currently expected to cover:

- more than one matrix-valid transcription profile,
- more than one matrix-valid evaluation profile,
- at least one mixed-provider transcription/evaluation pairing,
- and at least one fallback case where earlier compatible stage profiles are disabled and later compatible stage profiles are selected instead.

The concrete proven combinations live in the test suite, not in marketing-facing docs.

---

## Decision Rules

Going forward:

1. A configured provider capability declaration is not enough by itself.
2. Audited replacement targets must be expressed in package-owned terms.
3. Stage requirements must remain explicit for staged workflows.
4. Deterministic probes must use package fakes or deterministic runtime behavior only.
5. Provider SDK types must not leak into the matrix or conformance public semantics.

---

## Outcome of P1Y-I4

This issue is complete when:

- the audited provider capability matrix exists,
- deterministic conformance tests exist,
- false capability claims fail explicitly,
- staged capability mismatches fail explicitly,
- and later parity work can use the matrix as its test foundation.

That is now the operative contract for provider capability truth in the package.