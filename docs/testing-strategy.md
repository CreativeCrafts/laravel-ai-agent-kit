# SDK-Backed Testing Strategy

## Purpose

This document defines the canonical testing strategy for Laravel AI Agent Kit as an SDK-backed package.

The package is built on top of Laravel AI SDK, but its public contracts, workflow semantics, security defaults, telemetry rules, and package-facing result shapes remain package-owned. Tests must
reinforce that boundary.

Use this document as the authoritative guide for:

- choosing the correct test layer
- deciding whether to use package fakes, Laravel AI SDK fakes, or no fake layer
- keeping tests deterministic and network-free
- preventing vendor SDK types from leaking into package public-surface expectations

## Architectural Premise

Laravel AI Agent Kit is an opinionated Laravel AI application layer built on top of Laravel AI SDK.

- **Laravel AI SDK** is the internal execution substrate.
- **Laravel AI Agent Kit** owns:
		- workflow composition
		- prompt governance
		- tool governance
		- memory policy
		- resilience policy
		- security and compliance defaults
		- redacted telemetry
		- scaffolding
		- package-owned public contracts and DTOs

That means the test suite must distinguish between:

1. tests that validate **package semantics**
2. tests that validate **SDK alignment or bridge behavior**

Do not treat provider SDK payloads or SDK types as the public truth of this package.

## Test Taxonomy

### 1. Unit Tests

Use unit tests for pure package logic with the fewest dependencies possible.

Typical targets:

- DTO invariants
- config parsing and validation helpers
- schema validation
- retry and backoff calculations
- redactors
- encryption wrappers
- prompt rendering logic
- typed exception factories
- vector query/value validation

Guidance:

- Prefer no fake layer.
- Keep inputs explicit.
- Avoid container bootstrapping unless the unit actually depends on container behavior.
- Assert package-owned values and failure semantics directly.

### 2. Package Integration Tests

Use package integration tests when validating package-owned behavior across module boundaries.

Typical targets:

- blueprint execution
- orchestration semantics
- provider policy and failover rules
- memory continuation and retention behavior
- tool registration and authorization behavior
- package event emission and redaction
- package DTO result shapes
- package typed exceptions

Guidance:

- Prefer **package fakes**.
- Assert package-owned DTOs, package-owned traces, and package-owned exception semantics.
- Do not assert vendor SDK response classes in these tests.

### 3. Runtime Alignment Tests

Use runtime alignment tests when validating the package's internal bridge to Laravel AI SDK.

Typical targets:

- runtime bridge request mapping
- prompt and tool materialization into SDK-backed execution
- SDK event normalization inputs and outputs
- bridge-level metadata propagation
- substrate-facing behavior that should remain compatible with Laravel AI SDK

Guidance:

- Prefer **Laravel AI SDK fakes** or tightly scoped fake runtime behavior that exercises the bridge.
- These tests may inspect bridge-facing details, but the package public API still remains package-owned.
- Keep these tests focused. They are not replacements for higher-level package integration tests.

### 4. Contract and Conformance Tests

Use contract and conformance tests to prove a package-owned abstraction behaves consistently across implementations or declared capability sets.

Typical targets:

- provider capability conformance
- vector store contract suites
- memory driver contract behavior
- adapter compliance
- package-owned failure normalization behavior across provider profiles

Guidance:

- Use deterministic package or runtime fakes as appropriate.
- Assert package-defined capability semantics, not provider marketing claims.
- Negative-path coverage is required when a capability is unsupported or falsely declared.

## Fake-Layer Selection Rules

### Use Package Fakes When Testing Package Semantics

Choose package fakes when the test is about behavior owned by Laravel AI Agent Kit.

Examples:

- blueprint result DTO shape
- orchestration execution trees
- delegation and ownership transfer behavior
- package provider policy
- memory persistence semantics
- vector store behavior behind the package port
- package telemetry payload semantics
- redaction defaults
- package tool registry behavior

Typical package fakes include:

- `FakeAiRuntime`
- `FakeAgentOrchestrator`
- `FakeProviderPolicy`
- `FakeToolRunner`
- `FakeConversationStore`
- `FakeVectorStore`

### Use Laravel AI SDK Fakes When Testing SDK Bridge Alignment

Choose Laravel AI SDK fakes when the test is about how the package maps into or out of the Laravel AI SDK substrate.

Examples:

- runtime bridge mapping into SDK-backed execution
- SDK-backed prompt or tool materialization
- SDK event normalization
- substrate-level compatibility behavior

These tests should remain narrow and intentional. Do not use SDK fakes as the default for package behavior that is already package-owned.

### Use No Fake Layer for Pure Unit Logic

Do not introduce a fake layer unless the test actually needs one.

Examples:

- DTO validation
- redaction logic
- config validation
- retry calculation
- prompt interpolation
- exception construction

## What Counts as Package-Owned Semantics

When testing public package behavior, prefer assertions on these concepts:

- package-owned contracts
- package-owned request and result DTOs
- package-owned exceptions
- package-owned event payload semantics
- package-owned orchestration traces
- package-owned provider-profile and failover decisions
- package-owned memory and retention policies
- package-owned tool-governance outcomes

If the public behavior is described in `src/Contracts/**` or in README-documented package usage, that behavior should be asserted in package terms.

## What Not to Assert in Package Public-Surface Tests

Do not normalize these into package public-surface expectations unless the test is explicitly a runtime-alignment or bridge test:

- vendor SDK response classes
- raw provider-native payloads
- provider-specific event classes
- provider-specific exception types
- provider marketing terminology used as if it were a package contract
- SDK internal transport details

A bridge test may inspect substrate-facing details when necessary, but public package behavior should still be validated through package-owned semantics.

## Determinism Requirements

All package tests must remain deterministic by default.

Rules:

- no live provider calls
- no network access
- no provider credentials required
- stable fake responses
- explicit control of time when testing timeout, retry, backoff, or retention behavior
- no secret-bearing environment assumptions
- no flaky randomness
- no hidden dependence on external state

If a test becomes easier by making a real provider call, the test is almost certainly wrong for this package.

## Module-by-Module Guidance

### Core

Use package integration tests for:

- orchestration
- pipeline behavior
- execution trace semantics
- typed package failures

Use unit tests for:

- context DTO invariants
- pure orchestration helpers

### Runtime

Use runtime alignment tests for:

- request mapping
- SDK-backed execution bridge behavior
- substrate-level metadata propagation

Use package integration tests for:

- package-facing runtime result semantics
- normalized failure behavior once it is package-owned

### Prompts

Use unit tests for:

- interpolation
- version lookup
- missing variable behavior

Use package integration tests when prompt behavior is exercised as part of a blueprint or runtime path.

### Tools

Use unit tests for:

- schema validation
- invalid input rejection

Use package integration tests for:

- registration
- authorization behavior
- execution semantics
- provider-tool materialization where package policy is involved

### Memory

Use package integration tests for:

- conversation continuation
- retention behavior
- summarization triggers
- encrypted persistence behavior

Use contract-style tests across multiple drivers where applicable.

### Resilience

Use unit tests for:

- retry/backoff calculations
- budget evaluation rules

Use package integration tests for:

- failover semantics
- timeout behavior
- retry policy behavior across execution paths

### Observability

Use package integration tests for:

- event emission
- correlation propagation
- payload redaction
- package telemetry semantics

Use runtime alignment tests where SDK events are being normalized into package events.

### Vector

Use contract and adapter-compliance tests for:

- `VectorStoreInterface`
- adapter-specific conformance
- negative-path behavior for invalid documents or queries

### Blueprints

Use package integration tests for:

- final result DTO shapes
- package-facing semantics
- deterministic orchestration or staged workflow behavior
- typed package failure handling

Blueprint tests should not depend on live provider behavior.

## Recommended Assertion Style

Prefer assertions that answer:

- Did the package return the correct package-owned DTO?
- Did the package emit the correct package-owned event?
- Did the package raise the correct typed package exception?
- Did the package preserve the correct redaction and correlation behavior?
- Did the package enforce its documented policy surface?

Avoid assertions that primarily answer:

- Did the vendor SDK return the exact internal class I expected?
- Did the provider payload look exactly like one provider's API response?
- Did the package expose the underlying substrate shape directly?

## Downstream Issues This Strategy Must Unblock

This strategy is intended to support the next release-hardening work without reinterpretation:

- **#170** Tests: fakes behave like real flows from a package perspective
- **#224** Provider capability matrix + conformance suite
- **#225** Tests: cross-provider `TextToStructuredEvaluation` parity
- **#226** Tests: mixed-provider `AudioToTextToEvaluation` stage parity

Those issues should follow this testing doctrine rather than inventing their own fake or assertion model.

## Review Checklist for New Tests

Before merging a new test, ask:

1. Am I testing package semantics, runtime alignment, or a pure unit?
2. Did I choose the smallest correct fake layer?
3. Am I asserting package-owned behavior rather than vendor internals?
4. Is the test deterministic and network-free?
5. Would this test still make sense if the package changed internal SDK wiring without changing its public contract?

If the answer to the last question is no, the test likely belongs in a narrower runtime-alignment layer rather than a public package behavior layer.

## Summary

The shortest rule is:

- **unit tests** for pure package logic
- **package fakes** for package semantics
- **SDK fakes** for bridge alignment
- **contract tests** for cross-implementation guarantees
- **never** treat vendor SDK types as package public truth