# Contributing

## Testing Strategy

The package supports two complementary testing layers:

- **Laravel AI SDK fakes** for SDK-bridge and runtime-integration coverage.
- **Package fakes** for package-owned policy, orchestration, memory, tools, provider policy, and vector behavior.

Use package fakes when the behavior under test is owned by the package and should remain deterministic without depending on SDK internals.

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
- tool registration/execution flows owned by the package
- memory persistence or purge semantics without real infrastructure
- vector contract behavior without a real backend

Prefer Laravel AI SDK fakes when you need to validate the package’s SDK integration layer itself.

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