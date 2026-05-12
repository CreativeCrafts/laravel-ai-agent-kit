# Design: audio sources and multimodal audio-image structured evaluation

## Context

Agent Kit currently owns transcription as a modality runtime, but its public request DTO is base64-only. `TranscriptionRequest` accepts `base64Audio`, validates that it is non-empty, and carries prompt/provider options/language/diarization/timeout/provider/model metadata. `SdkTranscriptionRuntime` then always calls `Laravel\Ai\Transcription::fromBase64($request->base64Audio, $request->mimeType)` before applying provider options, language, diarization, timeout, and provider/model selection.

The underlying Laravel AI SDK supports additional transcription inputs independently of Agent Kit: `Transcription::fromBase64(...)`, `Transcription::fromPath(...)`, `Transcription::fromStorage(...)`, and `Transcription::fromUpload(...)`. Its pending transcription object also supports provider options, language, diarization, timeout, provider/model failover, and queued local/stored audio transcription. Therefore the missing capability is not in the SDK; it is in Agent Kit's package-owned abstraction layer.

Agent Kit runtime execution already supports structured output and attachments. `ExecutionRequest` accepts a schema and a list of Laravel AI `File` attachments; `SdkAiRuntime` builds a structured agent when a schema is present and passes effective attachments to the SDK agent prompt. The Laravel AI SDK image file abstraction supports base64, provider id, local path, remote URL, storage, and upload images. Therefore multimodal image/text structured evaluation can be implemented through Agent Kit runtime contracts without exposing provider-specific SDK calls to the user.

## Goals

- Let applications transcribe base64, local path, storage, uploaded file, and verified URL audio through Agent Kit without direct Laravel AI SDK calls.
- Keep all Laravel AI SDK mapping inside Agent Kit runtime/adapters.
- Preserve existing base64 transcription compatibility.
- Preserve prompted transcription and provider options behavior.
- Add an Agent Kit workflow/pipeline for audio transcription followed by image-plus-transcript structured evaluation.
- Make provider capability requirements explicit and fail closed when a provider lacks transcription, image input, or structured output support.
- Provide deterministic fakes/assertions for the new public surfaces.

## Non-Goals

- No direct exposure of `Laravel\Ai\Files\Audio`, `Base64Audio`, `LocalAudio`, `StoredAudio`, or `Laravel\Ai\Transcription` in public Agent Kit request APIs.
- No provider-specific OpenAI payload API.
- No structured streaming support.
- No application-specific scoring, persistence, or CEFR logic in the package blueprint.
- No guarantee that every provider supports every source kind; support must be verified and documented per SDK/provider path.

## Design Decisions

### D1 — Add `TranscriptionAudioSource`

Introduce a package-owned immutable DTO:

```php
namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality;

final readonly class TranscriptionAudioSource
{
    public static function fromBase64(string $base64, ?string $mimeType = null): self;
    public static function fromPath(string $path, ?string $mimeType = null): self;
    public static function fromStorage(string $path, ?string $disk = null, ?string $mimeType = null): self;
    public static function fromUpload(UploadedFile $file, ?string $mimeType = null): self;
    public static function fromUrl(string $url, ?string $mimeType = null): self;

    public function kind(): TranscriptionAudioSourceKind;
    public function payload(): string|UploadedFile;
    public function mimeType(): ?string;
    public function disk(): ?string;
}
```

Recommended enum:

```php
enum TranscriptionAudioSourceKind: string
{
    case Base64 = 'base64';
    case Path = 'path';
    case Storage = 'storage';
    case Upload = 'upload';
    case Url = 'url';
}
```

Validation rules:

- base64 payload must be non-empty;
- path payload must be non-empty;
- storage path must be non-empty;
- upload file must be a valid `UploadedFile` instance;
- URL must be absolute HTTP(S) when enabled;
- MIME type must be null or non-empty after trim;
- disk must be null or non-empty after trim.

### D2 — Extend `TranscriptionRequest` without breaking existing construction

Preferred non-breaking shape:

```php
final readonly class TranscriptionRequest
{
    public function __construct(
        public string $runId,
        public string $base64Audio = '',
        public ?string $mimeType = null,
        public ?string $language = null,
        public bool $diarize = false,
        public ?int $timeout = null,
        public ?string $provider = null,
        public ?string $model = null,
        public array $metadata = [],
        public ?string $prompt = null,
        public ?TranscriptionProviderOptions $providerOptions = null,
        public ?TranscriptionAudioSource $audioSource = null,
    ) {}

    public static function fromAudioSource(
        string $runId,
        TranscriptionAudioSource $audioSource,
        ?string $language = null,
        bool $diarize = false,
        ?int $timeout = null,
        ?string $provider = null,
        ?string $model = null,
        array $metadata = [],
        ?string $prompt = null,
        ?TranscriptionProviderOptions $providerOptions = null,
    ): self;
}
```

Compatibility behavior:

- Existing callers that pass `base64Audio` continue to work.
- New callers use `fromAudioSource(...)` or the appended `audioSource` field.
- Constructor validation treats request audio as valid when either `audioSource` is present or `base64Audio` is non-empty.
- If both are present, fail fast with `InvalidArgumentException` to avoid ambiguity.
- `mimeType` remains supported for legacy base64 construction; source-level MIME type is authoritative for `audioSource` construction.

Alternative: introduce `TranscriptionRequestV2`. Rejected unless backward compatibility becomes unmanageable; additive source support is preferable.

### D3 — Map audio source kinds inside `SdkTranscriptionRuntime`

Add a private factory method:

```php
private function pendingFromSource(TranscriptionRequest $request): PendingTranscriptionGeneration
{
    $source = $request->audioSource
        ?? TranscriptionAudioSource::fromBase64($request->base64Audio, $request->mimeType);

    return match ($source->kind()) {
        Base64 => Transcription::fromBase64($source->payload(), $source->mimeType()),
        Path => Transcription::fromPath($source->payload(), $source->mimeType()),
        Storage => Transcription::fromStorage($source->payload(), $source->disk()),
        Upload => Transcription::fromUpload($source->payload()),
        Url => $this->pendingFromUrl($source),
    };
}
```

URL transcription must be implemented only if the installed `laravel/ai` version/provider bridge supports remote transcribable audio. If not verified, `fromUrl(...)` should exist only behind an explicit unsupported-source exception, or be deferred from implementation while reserving the enum case. The OpenSpec requirement should mark URL support as provider/SDK verified rather than unconditional.

All existing post-construction behavior remains unchanged:

- prompt/provider options are passed through `providerOptions(...)`;
- language, diarization, timeout are applied;
- `generate($provider, $model)` is called;
- result mapping preserves transcript, provider, model, usage, segments, and metadata.

### D4 — Add source-aware fakes/assertions

Package fakes should record the full `TranscriptionRequest`, including `audioSource` kind and redacted/summarized payload metadata.

Assertions should allow:

```php
AgentKit::assertTranscriptionRequested(fn (TranscriptionRequest $request) =>
    $request->audioSource?->kind() === TranscriptionAudioSourceKind::Storage
);
```

Payload redaction:

- base64 content must not be logged in full;
- local/storage paths may be logged only as configured/redacted telemetry;
- upload filenames may be logged, but not file contents.

### D5 — Add multimodal audio-image structured evaluation request

Introduce a workflow request DTO:

```php
final readonly class AudioImageStructuredEvaluationRequest
{
    public function __construct(
        public string $runId,
        public TranscriptionAudioSource $audio,
        public EvaluationImageInput $image,
        public string $evaluationPrompt,
        public array $instructions = [],
        public Closure|ObjectSchema|string $schema,
        public ?string $transcriptionPrompt = null,
        public ?string $transcriptionProvider = null,
        public ?string $transcriptionModel = null,
        public ?string $evaluationProvider = null,
        public ?string $evaluationModel = null,
        public ?GenerationOptions $generationOptions = null,
        public array $metadata = [],
    ) {}
}
```

`EvaluationImageInput` should be a package-owned source DTO mirroring only the image variants needed by runtime attachments:

```php
final readonly class EvaluationImageInput
{
    public static function fromUrl(string $url): self;
    public static function fromBase64(string $base64, ?string $mimeType = null): self;
    public static function fromPath(string $path, ?string $mimeType = null): self;
    public static function fromStorage(string $path, ?string $disk = null): self;
    public static function fromUpload(UploadedFile $file, ?string $mimeType = null): self;
}
```

Agent Kit maps this DTO internally to Laravel AI SDK image attachments via `Image::fromUrl(...)`, `fromBase64(...)`, `fromPath(...)`, `fromStorage(...)`, or `fromUpload(...)`.

### D6 — Implement as a pipeline/blueprint, not a direct service

Add a package-owned blueprint or pipeline definition:

```php
final readonly class AudioImageStructuredEvaluation
{
    public function __construct(
        private TranscriptionRuntime $transcriptionRuntime,
        private AiRuntime $runtime,
        private ProviderCapabilityChecker $capabilities,
    ) {}

    public function evaluate(AudioImageStructuredEvaluationRequest $request): AudioImageStructuredEvaluationResult;
}
```

Internal stages:

1. Validate provider capabilities.
2. Transcribe audio with `TranscriptionRuntime`.
3. Build an `ExecutionRequest` using transcript text plus evaluation prompt/instructions.
4. Convert `EvaluationImageInput` to an SDK image `File` attachment internally.
5. Execute with schema through `AiRuntime::execute(...)`.
6. Return transcript, structured output, raw output, provider/model/usage metadata for both stages, and stage metadata.

This may be implemented directly as a blueprint class first, with queued pipeline integration as an optional dispatcher wrapper:

```php
AgentKit::audioImageStructuredEvaluation()->evaluate($request);
```

and optionally:

```php
AgentKit::queuedPipeline(AudioImageStructuredEvaluationPipeline::class)->dispatch($request);
```

### D7 — Provider capability gating

The workflow must verify, before dispatch when possible, that selected providers support the required capabilities:

- transcription provider: `audio_transcription`;
- evaluation provider: `text_generation`, `structured_output`, and `vision` or `image_input`.

Capability names should use existing provider definition capability strings where present. If Agent Kit currently treats capabilities as free-form strings, document the canonical names and add config validation for the new ones.

If capability validation fails, throw a package exception such as:

```php
UnsupportedMultimodalEvaluationProviderException
UnsupportedTranscriptionAudioSourceException
```

Failure messages should include provider name, required capability, and remediation.

### D8 — Result shape

```php
final readonly class AudioImageStructuredEvaluationResult
{
    public function __construct(
        public string $runId,
        public string $transcript,
        public array $structuredOutput,
        public string $output,
        public array $usage,
        public array $metadata,
    ) {}
}
```

Metadata should include:

- transcription provider/model;
- evaluation provider/model;
- transcription usage;
- evaluation usage;
- audio source kind;
- image input kind;
- schema class/object marker;
- prompt identifiers when provided by prompt repository integration.

### D9 — Empty transcript behavior

The generic workflow should not always reject empty transcripts. Some scoring workflows intentionally classify empty/malformed audio in the structured evaluation stage. Add a request option:

```php
public bool $allowEmptyTranscript = false
```

Default `false` preserves conservative behavior. Applications such as language scoring can set `true` so the structured evaluator decides the outcome.

### D10 — Documentation and SDK matrix updates

Update docs to state:

- applications should use `TranscriptionAudioSource` rather than direct SDK transcription constructors;
- SDK support for each source kind is mapped internally by Agent Kit;
- URL audio transcription is supported only when verified by the installed SDK/provider path;
- multimodal audio-image structured evaluation requires provider support for transcription, vision/image input, and structured output;
- structured streaming is not supported for this workflow.

Update the maintainer SDK capability matrix so this gap is closed as package-owned instead of an application direct-SDK escape hatch.

## Open Questions

1. Should URL audio transcription be included in the first implementation or deferred until a Laravel AI SDK `RemoteAudio` transcription path is verified?
2. Should the multimodal workflow live under `Blueprints`, `Core\Pipeline`, or both?
3. Should `EvaluationImageInput` be generalized to `RuntimeAttachmentInput` for future document/image workflows?
4. Should capability names standardize on `vision` or `image_input`?
5. Should `allowEmptyTranscript` default to `false` globally but be configurable per blueprint?
