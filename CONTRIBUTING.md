# Contributing

## Maintainer documentation

Contributor and maintainer process documentation lives under `docs/maintainers/`:

- [CI matrix](docs/maintainers/ci-matrix.md)
- [Release verification](docs/maintainers/release-verification.md)
- [SDK capability matrix](docs/maintainers/sdk-capability-matrix.md)
- [SDK async inventory](docs/maintainers/sdk-async-inventory.md)
- [Testing strategy](docs/maintainers/testing-strategy.md)

Public developer documentation should stay focused on application usage. Keep release process, SDK inventory, and repository-maintenance details in maintainer docs or the changelog.

## Testing Strategy

This package is an opinionated Laravel AI application layer built on top of Laravel AI SDK.

- **Laravel AI SDK** is the internal runtime substrate.
- **This package** owns workflow composition, prompt governance, tool governance, memory policy, resilience policy, redacted telemetry, scaffolding, and package-owned public contracts.

Tests must preserve that boundary. Contributors should validate **package semantics** through package-owned contracts and DTOs rather than normalizing vendor SDK types into package-facing expectations.

The canonical testing strategy for this repository lives in [`docs/maintainers/testing-strategy.md`](docs/maintainers/testing-strategy.md).

### Quick Rules

- Use **unit tests** for pure package logic such as validators, DTO invariants, retry calculations, redactors, and config validation helpers.
- Use **package fakes** when the behavior under test is owned by the package and should remain stable regardless of SDK internals.
- Use **Laravel AI SDK fakes** only when validating the package's runtime bridge or SDK-alignment behavior.
- Keep all tests **deterministic and network-free**.
- Do not write package public-surface tests that assert vendor SDK response classes, provider payloads, or SDK event classes.

## Package Fakes

The package ships first-class fakes under `CreativeCrafts\LaravelAiAgentKit\Testing\Fakes`:

- `FakeAiRuntime`
- `FakeAgentOrchestrator`
- `FakeProviderPolicy`
- `FakeToolRunner`
- `FakeConversationStore`
- `FakeVectorStore`

These fakes are intended to be instantiated directly and bound into the Laravel container inside tests.

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAiRuntime;

$fakeRuntime = new FakeAiRuntime([
    new ExecutionResult(runId: 'run-001', output: 'Fake output'),
]);

app()->instance(AiRuntime::class, $fakeRuntime);
~~~

For orchestration-specific flows, prefer the orchestration fake when you need deterministic delegation, resume, or ownership-transfer traces without running the real orchestrator:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAgentOrchestrator;

$fakeOrchestrator = (new FakeAgentOrchestrator())
    ->queueDelegationFlowResult(
        sourceAgent: 'support.agent',
        targetAgent: 'refund.agent',
        handoffSummary: 'Collect refund context and return the resolution summary.',
        finalOutput: ['workflow' => 'support_refund'],
    );

app()->instance(AgentOrchestrator::class, $fakeOrchestrator);
~~~

## Assertion Helpers

The package also ships assertion helpers under `CreativeCrafts\LaravelAiAgentKit\Testing\Assertions\PackageAssertions`.

These helpers are intended for common fake-driven expectations such as:

- runtime execution counts and last-request inspection
- default-provider selection and failover lookups
- tool execution assertions
- conversation existence/missing state assertions
- vector storage and deletion assertions
- orchestration execution counts and request inspection
- execution-tree, delegation, handoff-summary, and ownership-transfer assertions

~~~php
use CreativeCrafts\LaravelAiAgentKit\Testing\Assertions\PackageAssertions;

PackageAssertions::assertRuntimeExecutedTimes($fakeRuntime, 1);
PackageAssertions::assertLastRuntimeRequest($fakeRuntime, function ($request): void {
    expect($request->runId)->toBe('run-001');
});

PackageAssertions::assertDelegationOccurred($result, 'support.agent', 'refund.agent');
PackageAssertions::assertHandoffSummary(
    $result,
    'support.agent',
    'Collect refund context and return the resolution summary.',
);
~~~

The internal Pest test suite also wires convenience expectations for the same helpers, but the package-owned helper class remains the stable assertion surface.

## Usage Guidance

Prefer package fakes when you need to test:

- pipeline orchestration and step behavior
- package-owned multi-agent orchestration flows, including delegation approval and ownership transfer
- tool registration and execution flows owned by the package
- memory persistence or purge semantics without real infrastructure
- vector contract behavior without a real backend
- package-owned blueprint result DTOs and typed exceptions
- package-owned telemetry and redaction behavior

Prefer Laravel AI SDK fakes when you need to validate the package's SDK integration layer itself, such as:

- runtime bridge request and response mapping
- SDK-backed tool or provider-tool materialization
- SDK event normalization inputs and outputs
- substrate-adjacent behavior where the package is intentionally validating alignment with the SDK layer

Use assertion helpers to keep tests readable, but avoid hiding the package behavior under a large custom DSL. The helpers should clarify common expectations, not replace explicit flow setup.

## Scaffolding Commands

The package ships scaffolding commands for the current workflow surface:

- `php artisan ai:make:tool Support/LookupCustomer`
- `php artisan ai:make:prompt Support.Reply --prompt-version=2.1.0`
- `php artisan ai:make:agent Support/ReplyAgent`
- `php artisan ai:make:pipeline Support/ReplyPipeline`

The agent and pipeline commands use `ProjectInspector` to derive the active PSR-4 root namespace and source paths before generating files. They fail safely when the destination already exists unless
`--force` is supplied.

## Determinism Rules

- Do not use network calls in package tests.
- Use explicit timestamps when retention or time-based behavior matters.
- Keep fake behavior readable and close to package contracts rather than provider-specific internals.
- Keep assertion helpers focused on common package expectations rather than framework internals.
- Keep orchestration execution trees small and explicit in tests so parent-child ownership remains obvious.
- Freeze or control time when retries, backoff, retention, or timeout behavior is under test.
- Do not rely on secret-bearing environment configuration for deterministic tests.

## Public-Boundary Testing Rule

When the test is asserting a package-owned public surface, assert against package-owned concepts:

- package DTOs
- package contracts
- package exceptions
- package event payload semantics
- package redaction defaults

Avoid asserting these in package public-surface tests unless the test is explicitly a runtime-alignment or bridge test:

- vendor SDK response classes
- provider-native payload shapes
- raw provider event classes
- provider-specific error payloads
