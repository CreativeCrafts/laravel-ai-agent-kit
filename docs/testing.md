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
- transcription runtime
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

## Testing source-backed transcription

Use `FakeTranscriptionRuntime` when application code should stay at the Agent Kit abstraction layer and you want to assert the source kind, source metadata, prompt, provider, or model without live provider calls.

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\TranscriptionRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionAudioSource;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionRequest;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeTranscriptionRuntime;

$fakeTranscriptions = new FakeTranscriptionRuntime();
app()->instance(TranscriptionRuntime::class, $fakeTranscriptions);

app(TranscriptionRuntime::class)->transcribe(
    TranscriptionRequest::fromAudioSource(
        runId: 'tx-001',
        audioSource: TranscriptionAudioSource::fromStorage('answers/audio.mp3', 's3-audios', 'audio/mpeg'),
        provider: 'openai',
        model: 'gpt-4o-transcribe',
    ),
);

expect($fakeTranscriptions->lastRequest()?->resolvedAudioSource()->safeMetadata())
    ->toMatchArray([
        'kind' => 'storage',
        'disk' => 's3-audios',
        'reference' => 'answers/audio.mp3',
    ]);
~~~

`safeMetadata()` never exposes raw base64 audio or uploaded file contents. It reports source kind and safe identifiers such as disk/path, MIME type, upload filename, or payload length.

## Testing transcription prompts and provider options

When testing the Agent Kit to Laravel AI SDK transcription bridge, use Laravel AI SDK transcription fakes and assert that requested prompt/provider options appear in SDK provider options. This proves controlled options were not silently dropped before provider dispatch.

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\TranscriptionRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionProviderOptions;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionRequest;
use Laravel\Ai\Prompts\TranscriptionPrompt;
use Laravel\Ai\Transcription;

Transcription::fake(function (TranscriptionPrompt $prompt): string {
    $providerOptions = property_exists($prompt, 'providerOptions')
        ? $prompt->providerOptions
        : [];

    expect($providerOptions['chunking_strategy'] ?? null)->toBe('auto');

    return 'fake transcript';
})->preventStrayTranscriptions();

$result = app(TranscriptionRuntime::class)->transcribe(
    new TranscriptionRequest(
        runId: 'test-transcription-001',
        base64Audio: base64_encode('audio'),
        mimeType: 'audio/mpeg',
        diarize: true,
        provider: 'openai',
        model: 'gpt-4o-transcribe-diarize',
        providerOptions: new TranscriptionProviderOptions(chunkingStrategy: 'auto'),
    ),
);
~~~

Provider-option assertions are SDK-version-sensitive. If the installed Laravel AI SDK transcription pending object does not support provider options, Agent Kit raises its fail-fast unsupported prompt/provider-options exception instead of dropping `prompt` or `chunking_strategy` silently.

For application-facing tests that do not care about the SDK bridge, bind your own `TranscriptionRuntime` fake or test double and assert the received `TranscriptionRequest::$prompt` or `TranscriptionRequest::$providerOptions` directly.

## Testing audio-image structured evaluation

Bind `FakeTranscriptionRuntime` and `FakeAiRuntime` together to test multimodal workflows without live providers. The evaluation stage records its `ExecutionRequest`, including the schema and image attachment generated from `EvaluationImageInput`.

~~~php
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioImageStructuredEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\EvaluationImageInput;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\TranscriptionRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionAudioSource;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Facades\AgentKit;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeTranscriptionRuntime;

$fakeTranscriptions = new FakeTranscriptionRuntime();
$fakeRuntime = new FakeAiRuntime([
    new ExecutionResult(
        runId: 'score-001:evaluation',
        output: '{"level":"A2"}',
        structuredOutput: ['level' => 'A2'],
    ),
]);

app()->instance(TranscriptionRuntime::class, $fakeTranscriptions);
app()->instance(AiRuntime::class, $fakeRuntime);

$result = AgentKit::evaluateAudioImage(
    new AudioImageStructuredEvaluationRequest(
        runId: 'score-001',
        audio: TranscriptionAudioSource::fromBase64(base64_encode('audio'), 'audio/wav'),
        image: EvaluationImageInput::fromUrl('https://example.test/image.jpg'),
        evaluationPrompt: 'Evaluate the transcript and image.',
        schema: ScoreSchema::class,
    ),
);

expect($result->structuredOutput)->toBe(['level' => 'A2']);
expect($fakeRuntime->lastRequest()?->schema)->toBe(ScoreSchema::class);
~~~

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
