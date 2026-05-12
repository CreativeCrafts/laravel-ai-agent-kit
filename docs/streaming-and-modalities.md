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

## Blueprint integration

`AudioToTextToEvaluation` uses the transcription runtime when the audio reference is raw base64 or a `data:*;base64,...` URI. Opaque references such as `s3://...` may still flow through prompt/runtime paths depending on the configured workflow.

## Testing

Use package fakes for runtime and modality behavior. Do not call real provider APIs in tests.

See [Testing](testing.md).
