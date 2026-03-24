# Contributing

## Testing Strategy

The package supports two complementary testing layers:

- **Laravel AI SDK fakes** for SDK-bridge and runtime-integration coverage.
- **Package fakes** for package-owned policy, orchestration, memory, tools, provider policy, and vector behavior.

Use package fakes when the behavior under test is owned by the package and should remain deterministic without depending on SDK internals.

## Package Fakes

The package ships first-class fakes under `CreativeCrafts\LaravelAiAgentKit\Testing\Fakes`:

- `FakeAiRuntime`
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

## Usage Guidance

Prefer package fakes when you need to test:

- pipeline orchestration and step behavior
- package-owned provider policy and failover rules
- tool registration/execution flows owned by the package
- memory persistence or purge semantics without real infrastructure
- vector contract behavior without a real backend

Prefer Laravel AI SDK fakes when you need to validate the package’s SDK integration layer itself.

## Determinism Rules

- Do not use network calls in package tests.
- Use explicit timestamps when retention or time-based behavior matters.
- Keep fake behavior readable and close to package contracts rather than provider-specific internals.