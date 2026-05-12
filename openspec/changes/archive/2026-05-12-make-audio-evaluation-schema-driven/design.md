# Design: schema-driven audio evaluation

## Scope

This change turns `AudioToTextToEvaluation` from a narrow opinionated result shape into a reusable two-stage workflow primitive:

1. transcribe audio;
2. evaluate the transcript using a caller-provided structured output schema.

The existing default evaluation behavior should remain available for backward compatibility, either as the default when no schema is provided or as a named preset/compatibility path.

## Current problem

`AudioToTextToEvaluationResult` is too strict for real application workflows. A package-owned fixed shape is useful for examples, but it cannot represent support QA, sales qualification, compliance review, coaching summaries, incident review, and other domain-specific outputs without brittle post-processing.

The package already has structured runtime support. The audio blueprint should reuse that pattern rather than hard-code one business evaluation shape.

## Proposed API

### Request

Extend `AudioToTextToEvaluationRequest` with optional schema-driven fields.

Recommended shape:

```php
new AudioToTextToEvaluationRequest(
    audioReference: $audio,
    subject: 'support call',
    prompt: 'Evaluate this call for compliance and customer outcome.',
    schema: SupportCallQaSchema::class,
    instructions: [
        'Return only structured output matching the schema.',
    ],
)
```

Schema should support the same forms already supported by runtime structured output where practical:

- `Closure`
- `ObjectSchema`
- `class-string` implementing Laravel AI structured output contracts

The request may continue to support existing fields such as enabled dimensions, prompt version, subject, and context for the default preset.

### Result

Expose both transcription information and caller-defined structured output.

Recommended result fields:

```php
final readonly class AudioToTextToEvaluationResult
{
    public function __construct(
        public string $transcript,
        public array $structuredOutput,
        public array $segments = [],
        public array $metadata = [],
        public ?string $transcriptionProvider = null,
        public ?string $transcriptionModel = null,
        public ?string $evaluationProvider = null,
        public ?string $evaluationModel = null,
        public array $usage = [],
    ) {
    }
}
```

Avoid mandatory typed hydration in the first pass. `array $structuredOutput` keeps the contract stable and avoids coupling the package to arbitrary user DTO constructors. Typed hydration can be a later opt-in proposal.

### Backward compatibility

Use one of these approaches:

1. **Default compatibility mode:** if no schema is provided, preserve the existing default evaluation output and `toArray()` shape as much as possible.
2. **Named preset:** move the existing opinionated evaluation to a clearly named preset while keeping old constructor/request defaults working.

Prefer default compatibility mode first to reduce release risk.

## Workflow

### Stage 1: transcription

- Use `TranscriptionRuntime` when the audio reference is decodable by the current blueprint logic.
- Preserve transcript text and transcription segments.
- Preserve transcription provider/model metadata.

### Stage 2: structured evaluation

- Build an `ExecutionRequest` for the transcript evaluation stage.
- Include the caller-provided schema when present.
- Include prompt/instructions/context from the request.
- Use structured runtime output when available.
- If schema is provided and structured output is missing or invalid, fail with a typed blueprint exception.
- If schema is omitted, preserve the existing default evaluation path.

## Failure behavior

Schema-resolution failures should surface as blueprint failures with the original runtime/schema exception available as previous exception.

Transcription failures and evaluation failures should remain distinguishable in metadata or exception messages so callers can tell which stage failed.

## Testing

Add tests for:

- custom class-string schema result;
- `ObjectSchema` result;
- closure schema result;
- transcript and diarized segments are present in the final result;
- existing default/no-schema behavior still works;
- schema-resolution failure wraps/propagates predictably;
- fake runtime/fake transcription flows require no live provider calls.

## Documentation

Update blueprint docs to show:

- default quick-start preset;
- schema-driven custom result example;
- how transcript/segments/metadata are returned;
- when to use the lower-level `TranscriptionRuntime` directly instead of the blueprint.
