# Design: transcription prompts

## Scope

This change adds package-owned support for custom transcription prompts on Agent Kit transcription requests.

The goal is to let applications and package blueprints influence transcription behavior without bypassing Agent Kit. The prompt must be carried through the modality runtime when the installed Laravel AI SDK/provider path supports it, and it must never be silently ignored.

This change does not introduce arbitrary provider payload passthrough for transcription. Provider-specific options remain separate from prompt support.

## Current behavior

`TranscriptionRequest` currently contains:

- `runId`
- `base64Audio`
- `mimeType`
- `language`
- `diarize`
- `timeout`
- `provider`
- `model`
- `metadata`

`SdkTranscriptionRuntime` maps those fields to Laravel AI SDK by calling:

- `Transcription::fromBase64(...)`
- `language(...)` when language is present
- `diarize()` when diarization is enabled
- `timeout(...)` when timeout is present
- `generate($provider, $model)`

No transcription prompt is represented in the Agent Kit request DTO, and no prompt is forwarded to the SDK bridge.

## Proposed request shape

Add an optional nullable prompt field to `TranscriptionRequest`:

```php
final readonly class TranscriptionRequest
{
    public function __construct(
        public string $runId,
        public string $base64Audio,
        public ?string $mimeType = null,
        public ?string $language = null,
        public bool $diarize = false,
        public ?int $timeout = null,
        public ?string $provider = null,
        public ?string $model = null,
        public array $metadata = [],
        public ?string $prompt = null,
    ) {
        // validation
    }
}
```

The field is intentionally appended after `metadata` to avoid breaking existing positional constructor calls.

## Validation

`TranscriptionRequest` validation should enforce:

- `runId` remains non-empty.
- `base64Audio` remains non-empty.
- `timeout` remains `null` or `>= 1`.
- `prompt` must be `null` or a non-empty string after trimming.

The request DTO should not validate provider/model-specific prompt support. Runtime support depends on the installed Laravel AI SDK version and provider bridge.

## SDK bridge strategy

`SdkTranscriptionRuntime` should forward the prompt only when present.

Recommended approach:

```php
$pending = Transcription::fromBase64($request->base64Audio, $request->mimeType);

if ($request->language !== null) {
    $pending = $pending->language($request->language);
}

if ($request->prompt !== null) {
    if (method_exists($pending, 'prompt')) {
        $pending = $pending->prompt($request->prompt);
    } elseif (method_exists($pending, 'instructions')) {
        $pending = $pending->instructions($request->prompt);
    } else {
        throw new UnsupportedTranscriptionPromptException(...);
    }
}

if ($request->diarize) {
    $pending = $pending->diarize();
}

if ($request->timeout !== null) {
    $pending = $pending->timeout($request->timeout);
}

$response = $pending->generate($request->provider, $request->model);
```

The implementation should audit the installed Laravel AI SDK at implementation time and choose the actual supported method name. If there is only one supported method, prefer direct use plus a compatibility guard rather than retaining unnecessary method-name branching.

## Failure behavior

Prompted transcription must fail explicitly if the prompt cannot be honored.

Add a package exception such as:

```php
final class UnsupportedTranscriptionPromptException extends RuntimeException
{
}
```

The exception message should include enough remediation detail for maintainers:

- prompt support was requested;
- the installed Laravel AI SDK transcription pending object does not expose a supported prompt method;
- upgrade Laravel AI SDK or use an SDK/provider bridge that supports prompted transcription.

Silent prompt dropping is not allowed.

## Blueprint integration

Audio transcription blueprints that already render transcription prompts should pass the rendered prompt into `TranscriptionRequest`.

For `AudioToTextToEvaluation`-style flows:

- Keep prompt name/version metadata for observability.
- Render the transcription prompt before calling `TranscriptionRuntime`.
- Pass rendered prompt text through `TranscriptionRequest::$prompt`.
- Preserve existing behavior when no prompt is configured.

If the current prompt execution mapper only supports full runtime execution and not render-only access, add a small render-only method/service so transcription prompt rendering does not require a text-generation call.

## Testing strategy

Tests should cover DTO validation, runtime forwarding, failure behavior, blueprint propagation, and fake assertions.

### DTO tests

- Request without prompt behaves exactly as before.
- Request with non-empty prompt stores the prompt.
- Blank prompt throws `InvalidArgumentException`.

### SDK bridge tests

- When the pending transcription object supports prompt forwarding, `SdkTranscriptionRuntime` forwards the prompt before `generate(...)`.
- When no prompt is supplied, existing runtime behavior is unchanged.
- When a prompt is supplied but the pending object has no supported prompt method, runtime throws `UnsupportedTranscriptionPromptException` before provider dispatch.

### Blueprint tests

- Audio-to-text-to-evaluation transcription stage passes the rendered transcription prompt to `TranscriptionRequest`.
- Prompt name/version remain in metadata.
- Workflows without a transcription prompt continue to work.

### Fake/assertion tests

Package fakes or test doubles should let callers assert:

- last transcription request prompt equals the expected rendered prompt;
- no live provider call is required;
- prompt is absent when omitted.

## Documentation

Update modality and testing docs to explain:

- when to use transcription prompts;
- prompt support is provider/SDK-dependent;
- Agent Kit fails fast rather than silently dropping prompts;
- examples for language-assessment transcription prompts that preserve hesitations, pauses, false starts, grammar mistakes, and locale-specific characters.

## Compatibility

This is an additive public API change. Existing callers that do not pass `prompt` should see no behavior change.

Existing positional calls that pass `metadata` remain valid because `prompt` is appended after `metadata`.

If the implementation introduces a new exception class, it should live under the existing package exception namespace and be documented as a runtime configuration/capability error.
