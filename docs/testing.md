# Testing

Agent Kit is designed for deterministic tests. Application tests should use package-owned fakes and assertions instead of live providers or provider-native payloads.

## Basic runtime fake

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
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
~~~

## Orchestration fake

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAgentOrchestrator;

$fakeOrchestrator = new FakeAgentOrchestrator();

app()->instance(AgentOrchestrator::class, $fakeOrchestrator);
~~~

Use orchestration fakes when the application test cares about the result shape or workflow handoff behavior but not the real orchestrator internals.

## Other package fakes

The package includes fakes for common surfaces:

- runtime execution
- orchestration
- provider policy
- tool running
- conversation storage
- vector storage

Bind the fake into the Laravel container for the contract your application code depends on.

Facade modality methods such as `AgentKit::embed()`, `AgentKit::transcribe()`, `AgentKit::generateImage()`, `AgentKit::rerank()`, and `AgentKit::generateAudio()` resolve their runtime contracts from the container. Bind a test double for the relevant modality contract when testing application code through the facade.

## Assertion helpers

The package exposes helper assertions under `CreativeCrafts\LaravelAiAgentKit\Testing\Assertions\PackageAssertions` for common fake-driven checks.

~~~php
use CreativeCrafts\LaravelAiAgentKit\Testing\Assertions\PackageAssertions;

PackageAssertions::assertRuntimeExecutedTimes($fakeRuntime, 1);
PackageAssertions::assertLastRuntimeRequest($fakeRuntime, function ($request): void {
    expect($request->runId)->toBe('run-test-001');
});
~~~

## Testing transcription prompts

When testing the Agent Kit to Laravel AI SDK transcription bridge, use Laravel AI SDK transcription fakes and assert that prompted transcription appears in provider options. This proves the prompt was not silently dropped before provider dispatch.

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\TranscriptionRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionRequest;
use Laravel\Ai\Prompts\TranscriptionPrompt;
use Laravel\Ai\Transcription;

Transcription::fake(function (TranscriptionPrompt $prompt): string {
    expect($prompt->providerOptions['prompt'] ?? null)
        ->toBe('Transcribe verbatim and preserve pauses.');

    return 'fake transcript';
})->preventStrayTranscriptions();

$result = app(TranscriptionRuntime::class)->transcribe(
    new TranscriptionRequest(
        runId: 'test-transcription-001',
        base64Audio: base64_encode('audio'),
        mimeType: 'audio/mpeg',
        provider: 'openai',
        model: 'gpt-4o-transcribe',
        prompt: 'Transcribe verbatim and preserve pauses.',
    ),
);
~~~

For application-facing tests that do not care about the SDK bridge, bind your own `TranscriptionRuntime` fake or test double and assert the received `TranscriptionRequest::$prompt` directly.

## What to test with package fakes

Use package fakes for:

- blueprint result DTOs
- orchestration traces and delegation outcomes
- tool registration and authorization paths
- memory persistence behavior
- vector store interactions
- telemetry and redaction expectations
- typed package exceptions

## What not to do

Do not make live provider calls in package or application tests by default. Avoid tests that require provider credentials, network access, real external vector stores, or provider-native response classes.

## When to use Laravel AI SDK fakes

Use Laravel AI SDK fakes only for tests that specifically validate the internal bridge between Agent Kit and Laravel AI SDK. Application-facing workflow tests should usually stay in package-owned terms.

Use SDK fakes directly when your application intentionally uses direct Laravel AI SDK jobs, provider-native tools, provider-hosted retrieval, or SDK-specific modality behavior outside the Agent Kit envelope.

## Determinism checklist

- Use stable fake responses.
- Control time for retention, timeout, retry, or backoff behavior.
- Keep queued job payloads small and serializable.
- Avoid credential-bearing environment assumptions.
- Assert package-owned DTOs, exceptions, events, and traces.

Contributor-focused testing doctrine lives in `docs/maintainers/testing-strategy.md`.
