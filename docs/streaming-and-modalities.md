# Streaming and modalities

Agent Kit exposes package-owned runtime contracts for streaming text and non-chat modalities. The default implementations bridge to Laravel AI SDK behind package contracts.

## Streaming text

Use `StreamingAiRuntime` for non-schema streaming text responses.

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\StreamingAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;

final class StreamSummary
{
    public function __construct(
        private StreamingAiRuntime $runtime,
    ) {
    }

    public function handle(): iterable
    {
        return $this->runtime->executeStream(
            new ExecutionRequest(
                runId: 'stream-001',
                prompt: 'Summarize the refund policy in two sentences.',
            ),
        );
    }
}
~~~

Streaming returns ordered chunks followed by one terminal completion or failure value. Structured-output requests are not supported for streaming; use normal runtime execution for schema-backed calls.

## Stream failures

Provider and runtime failures are normalized into package-owned `StreamFailure` terminal values where possible. This includes failures thrown while creating the provider stream and failures thrown during stream iteration.

Applications consuming `executeStream()` should handle three event families:

- `StreamChunk` for ordered partial output
- `StreamComplete` for successful terminal output
- `StreamFailure` for terminal failure state

Failure telemetry is emitted through redacted `RuntimeStreamFailed` events when an event dispatcher is available.

## Provider health accounting

Streaming provider health is recorded from the terminal stream outcome, not stream creation alone.

- Stream creation failures record provider failure and may fail over before any chunks are emitted.
- Terminal provider stream errors and provider-failure iteration exceptions record provider failure.
- Successful stream completion records provider success once, after completion processing succeeds.
- Package-local completion failures, such as budget or memory reconciliation failures, do not count as provider failures unless they are normalized as provider failures.

Mid-stream failures remain terminal and are not replayed against another provider.

## Optional broadcast telemetry

Set `runtime.streaming.broadcast_channel` or request metadata `streaming_broadcast_channel` to emit redacted streaming events for public Echo channels. Payloads contain safe identifiers, counts, and lengths, not raw prompt content.

## Modality runtimes

Modality contracts live under package-owned namespaces and default to SDK-backed drivers:

| Modality | Contract purpose |
|----------|------------------|
| Transcription | audio to text |
| Embeddings | text to vectors |
| Image generation | prompt to image output |
| Reranking | ranking candidate documents |
| Audio generation | text to speech/audio output |

Configure per modality:

~~~php
'modalities' => [
    'transcription' => ['default_driver' => 'sdk'],
    'embeddings' => ['default_driver' => 'sdk'],
    'image_generation' => ['default_driver' => 'sdk'],
    'reranking' => ['default_driver' => 'sdk'],
    'audio_generation' => ['default_driver' => 'sdk'],
],
~~~

You may replace a modality driver with a class that implements the corresponding package contract.

## Transcription audio sources

Use `TranscriptionAudioSource` when audio is already stored, local, uploaded, or base64 encoded. This keeps applications on Agent Kit contracts and prevents direct calls to the underlying Laravel AI SDK transcription constructors.

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\TranscriptionRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionAudioSource;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionRequest;

$result = app(TranscriptionRuntime::class)->transcribe(
    TranscriptionRequest::fromAudioSource(
        runId: 'transcription-storage-001',
        audioSource: TranscriptionAudioSource::fromStorage(
            path: 'language-tests/answers/audio.mp3',
            disk: 's3-audios',
            mimeType: 'audio/mpeg',
        ),
        provider: 'openai',
        model: 'gpt-4o-transcribe',
        prompt: 'Transcribe verbatim. Preserve pauses and hesitations.',
    ),
);
~~~

Supported source factories:

~~~php
TranscriptionAudioSource::fromBase64($base64Audio, 'audio/wav');
TranscriptionAudioSource::fromPath('/tmp/audio.mp3', 'audio/mpeg');
TranscriptionAudioSource::fromStorage('answers/audio.mp3', 's3-audios', 'audio/mpeg');
TranscriptionAudioSource::fromUpload($uploadedFile, 'audio/mpeg');
~~~

`TranscriptionAudioSource::fromUrl(...)` is intentionally fail-closed in the SDK-backed runtime until remote URL transcription is verified for the installed Laravel AI SDK/provider path. Use storage/path/base64/upload sources or provide a custom `TranscriptionRuntime` when remote audio URLs are required.

Existing `new TranscriptionRequest(base64Audio: ...)` calls remain supported. New code should prefer `TranscriptionRequest::fromAudioSource(...)` for any non-base64 input.

Source metadata is redacted/summarized: base64 payloads and upload contents are not emitted in full. Tests can assert source kind, MIME type, disk, path/reference, and prompt/model/provider values through `FakeTranscriptionRuntime`.

## Transcription prompts

Use `TranscriptionRequest::$prompt` when the transcription stage must preserve domain-specific spoken features such as pauses, hesitations, false starts, grammar mistakes, uncertainty markers, locale-specific characters, or verbatim phrasing.

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\TranscriptionRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionRequest;

final class TranscribeLanguageTestAudio
{
    public function __construct(
        private TranscriptionRuntime $runtime,
    ) {
    }

    public function handle(string $base64Audio): string
    {
        $result = $this->runtime->transcribe(
            new TranscriptionRequest(
                runId: 'transcription-001',
                base64Audio: $base64Audio,
                mimeType: 'audio/mpeg',
                language: 'sv',
                provider: 'openai',
                model: 'gpt-4o-transcribe',
                prompt: 'Transcribe verbatim. Preserve pauses, hesitations, false starts, and Swedish characters.',
            ),
        );

        return $result->transcript;
    }
}
~~~

Prompt support depends on the installed Laravel AI SDK/provider path. Agent Kit forwards prompts through Laravel AI SDK transcription provider options when supported. If a prompted transcription request cannot be honored by the installed SDK path, Agent Kit fails fast instead of silently dropping the prompt.

OpenAI does not support `prompt` for diarized transcription. Use unprompted diarized transcription, or keep diarized/provider-specific option support separate from prompt-driven transcription.

## Diarized transcription provider options

Use `TranscriptionProviderOptions` for controlled provider-specific transcription options. The first supported option is `chunkingStrategy=auto`, intended for OpenAI diarized transcription models such as `gpt-4o-transcribe-diarize` when longer audio requires automatic chunking.

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\TranscriptionRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionProviderOptions;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionRequest;

$result = app(TranscriptionRuntime::class)->transcribe(
    new TranscriptionRequest(
        runId: 'diarized-transcription-001',
        base64Audio: $base64Audio,
        mimeType: 'audio/mpeg',
        diarize: true,
        provider: 'openai',
        model: 'gpt-4o-transcribe-diarize',
        providerOptions: new TranscriptionProviderOptions(
            chunkingStrategy: TranscriptionProviderOptions::CHUNKING_STRATEGY_AUTO,
        ),
    ),
);
~~~

Agent Kit validates controlled options before provider dispatch. Unsupported chunking strategy values fail during `TranscriptionProviderOptions` construction. `chunkingStrategy` also requires `diarize: true` on the transcription request.

Provider-option support depends on the installed Laravel AI SDK transcription pending object. If the SDK path cannot honor provider options, Agent Kit fails fast instead of silently dropping `chunking_strategy`.

## Blueprint integration

`AudioToTextToEvaluation` uses the transcription runtime when the audio reference is raw base64 or a `data:*;base64,...` URI. Opaque references such as `s3://...` may still flow through prompt/runtime paths depending on the configured workflow.

When the blueprint uses `TranscriptionRuntime`, it renders the configured transcription prompt and passes that rendered prompt into `TranscriptionRequest::$prompt`. Prompt name and version remain in request metadata for observability.

## Testing

Use package fakes for runtime and modality behavior. Do not call real provider APIs in tests.

For transcription prompt tests, fake the Laravel AI transcription gateway and assert that the prompt appears in transcription provider options. For controlled provider-option tests, assert `chunking_strategy` appears in the same provider-options payload when the installed SDK supports provider options; otherwise assert the package fail-fast exception.

For source-backed transcription tests, bind `CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeTranscriptionRuntime` to `TranscriptionRuntime` and assert the recorded `TranscriptionRequest::resolvedAudioSource()` kind and safe metadata.

See [Testing](testing.md).
