# Design: diarized transcription provider options

## Scope

This change adds a package-owned way to request provider-specific diarized transcription options, focused on OpenAI `gpt-4o-transcribe-diarize` and `chunking_strategy`.

It does not introduce a generic unvalidated provider request body passthrough for transcription.

## Current behavior

Agent Kit `SdkTranscriptionRuntime` maps `TranscriptionRequest` to Laravel AI SDK transcription calls:

- base64 audio
- MIME type
- language
- diarize
- timeout
- provider
- model

Laravel AI SDK maps OpenAI diarization to `response_format=diarized_json` when `diarize()` is enabled. It does not currently expose transcription `chunking_strategy`.

## Proposed request shape

Prefer a typed additive option object or typed request fields over an arbitrary payload array.

Recommended DTO additions:

```php
public readonly ?TranscriptionProviderOptions $providerOptions = null;
```

with:

```php
final readonly class TranscriptionProviderOptions
{
    public function __construct(
        public ?string $chunkingStrategy = null,
    ) {
    }
}
```

Supported values for `chunkingStrategy`:

- `null`: do not request chunking explicitly
- `auto`: request provider automatic chunking

Future values should be added only after provider compatibility is reviewed.

## Runtime strategy

### Preferred upstream-compatible path

If Laravel AI SDK adds transcription provider options, `SdkTranscriptionRuntime` should pass the typed options through the SDK.

### Package bridge path

If the installed Laravel AI SDK version still cannot pass `chunking_strategy`, Agent Kit can add a package-owned provider-specific bridge for OpenAI transcription while keeping the public request shape provider-neutral.

The bridge should activate only when:

- provider resolves to OpenAI;
- `diarize` is true;
- model is `gpt-4o-transcribe-diarize` or another explicitly supported diarization model;
- `providerOptions->chunkingStrategy` is `auto`.

Otherwise, continue using the Laravel AI SDK transcription runtime path.

## Validation

Validation should reject unsupported option values early:

- `chunkingStrategy` must be `null` or `auto`.
- If `chunkingStrategy` is set while `diarize` is false, fail fast or ignore only if explicitly documented. Prefer fail-fast to avoid silent misconfiguration.
- If `chunkingStrategy` is set for a non-OpenAI provider and the runtime cannot support it, fail fast with a typed package exception.

## Result mapping

Diarized transcription should continue to map provider segments into package `TranscriptionSegmentResult` values:

- text
- speaker
- start seconds
- end seconds

Existing `TranscriptionResult` shape should not require breaking changes.

## Testing

Tests should cover:

- existing non-diarized transcription behavior remains unchanged;
- diarized transcription still requests SDK diarization;
- `chunkingStrategy=auto` is accepted for OpenAI diarized transcription;
- unsupported `chunkingStrategy` values fail validation;
- chunking without `diarize=true` fails validation;
- provider-specific bridge or SDK passthrough sends `chunking_strategy=auto` when supported;
- fakes can assert requested transcription options without live provider calls.

## Documentation

Update public docs to clarify:

- `gpt-4o-transcribe-diarize` is a transcription model, not a chat/runtime model;
- short diarized OpenAI transcription can work through `diarize=true` and the diarized model;
- longer diarized audio needs chunking support;
- Agent Kit exposes only controlled transcription provider options, not arbitrary provider payloads.
