# Testing with fakes

The package includes package-owned fakes for runtime, provider policy, tool execution, conversation storage, vector storage, and orchestration. These can be bound directly into the Laravel
container for deterministic tests.

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAiRuntime;

$fakeRuntime = new FakeAiRuntime([
    new ExecutionResult(
        runId: 'run-test-001',
        output: 'Fake runtime output',
        provider: 'openai',
        model: 'gpt-test',
    ),
]);

app()->instance(AiRuntime::class, $fakeRuntime);
app()->instance(AgentOrchestrator::class, new FakeAgentOrchestrator());
~~~

The package also exposes assertion helpers and Pest expectations for common fake-driven flows. See `CONTRIBUTING.md` and the package test suite for usage patterns.

## See also

- [README quick start](../README.md#usage)
- [Pipelines, queues, memory, and vectors](pipelines-queues-and-memory.md)
