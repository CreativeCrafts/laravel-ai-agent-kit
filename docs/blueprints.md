# Blueprints

Blueprints are package-owned workflows with typed request and result DTOs. They hide internal orchestration details and return stable application-facing results.

## Text to structured evaluation

Use `TextToStructuredEvaluation` when you want to evaluate text and receive one structured result.

~~~php
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluation;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluationRequest;

final class EvaluateSupportReply
{
    public function __construct(
        private TextToStructuredEvaluation $evaluation,
    ) {
    }

    public function handle(string $reply): array
    {
        $result = $this->evaluation->evaluate(
            new TextToStructuredEvaluationRequest(
                subject: 'support reply',
                text: $reply,
                enabledDimensions: ['clarity', 'accuracy', 'completeness'],
                promptVersion: '1.0.0',
            ),
        );

        return $result->toArray();
    }
}
~~~

The result includes:

- `subject`
- `summary`
- `recommendedAction`
- `confidence`
- `enabledDimensions`
- dimension results keyed by dimension name
- prompt metadata
- orchestration metadata and trace information

The blueprint requests structured output through the package runtime. If a provider cannot return structured output directly, the package can fall back to bounded text normalization while still returning a package-owned result DTO.

## Audio to text to evaluation

Use `AudioToTextToEvaluation` when you want to transcribe audio and evaluate the transcript in one workflow.

~~~php
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Facades\AgentKit;

$result = AgentKit::evaluateAudio(
    new AudioToTextToEvaluationRequest(
        subject: 'support call',
        audioReference: 's3://bucket/audio/support-call.wav',
        audioMimeType: 'audio/wav',
        enabledDimensions: ['clarity', 'accuracy'],
        transcriptionPromptVersion: '1.0.0',
        evaluationPromptVersion: '1.0.0',
    ),
);
~~~

By default, the blueprint returns the same package-owned evaluation shape as `TextToStructuredEvaluation`, plus audio-specific data:

- `audioReference`
- `transcript`
- transcription prompt metadata
- evaluation prompt metadata
- optional transcription segments
- transcription and evaluation provider/model metadata
- transcription and evaluation usage metadata
- raw `structuredOutput`

The transcription stage needs a provider profile with `audio_transcription`. The evaluation stage needs a provider profile with `text_generation` and `structured_output`.

## Schema-driven audio evaluation

Pass `schema` when the application owns the desired result shape. The schema is forwarded to the evaluation-stage runtime request and the final result exposes the raw structured payload through `AudioToTextToEvaluationResult::$structuredOutput` and `toArray()['structured_output']`.

~~~php
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Facades\AgentKit;
use Laravel\Ai\ObjectSchema;

$result = AgentKit::evaluateAudio(
    new AudioToTextToEvaluationRequest(
        subject: 'sales qualification call',
        audioReference: $audioReference,
        audioMimeType: 'audio/mpeg',
        enabledDimensions: ['custom_schema'],
        transcriptionPromptVersion: '1.0.0',
        evaluationPromptVersion: '1.0.0',
        schema: new ObjectSchema([], name: 'sales_qualification_result'),
    ),
);

$structured = $result->structuredOutput;
~~~

Supported schema forms are the same forms accepted by `ExecutionRequest::$schema` where practical:

- `Closure`
- `ObjectSchema`
- class-string schema identifiers

When `schema` is provided, the evaluation stage requires non-empty structured output. The compatibility fields (`summary`, `recommendedAction`, `confidence`, and `dimensions`) remain available for existing consumers, but the application-specific contract should read from `structuredOutput`.

If no schema is provided, the existing default evaluation behavior remains unchanged.

## Audio-image structured evaluation

Use `AudioImageStructuredEvaluation` when the evaluation needs both the transcript and an image in the same structured runtime call. This is the Agent Kit-first path for providers such as OpenAI when the configured provider supports transcription, image input, and structured output.

~~~php
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioImageStructuredEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\EvaluationImageInput;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionAudioSource;
use CreativeCrafts\LaravelAiAgentKit\Facades\AgentKit;

$result = AgentKit::evaluateAudioImage(
    new AudioImageStructuredEvaluationRequest(
        runId: 'language-score-001',
        audio: TranscriptionAudioSource::fromStorage(
            path: 'language-tests/answers/audio.mp3',
            disk: 's3-audios',
            mimeType: 'audio/mpeg',
        ),
        image: EvaluationImageInput::fromUrl('https://example.test/question-image.jpg'),
        evaluationPrompt: 'Evaluate the transcript against the image and return the requested schema.',
        schema: SwedishEvaluationSchema::class,
        instructions: [
            'Return strict structured output only.',
        ],
        transcriptionProvider: 'openai-transcription',
        transcriptionModel: 'gpt-4o-transcribe',
        evaluationProvider: 'openai-vision',
        evaluationModel: 'gpt-4.1-mini',
        strictStructuredOutput: true,
    ),
);

$structured = $result->structuredOutput;
~~~

The workflow runs two package-owned stages:

1. `TranscriptionRuntime` transcribes the `TranscriptionAudioSource`.
2. `AiRuntime` evaluates the transcript plus `EvaluationImageInput` as a structured request.

The evaluation provider must advertise `text_generation`, `structured_output`, and either `image_input` or `vision` when provider metadata is configured. The transcription provider must advertise `audio_transcription` when configured. If provider metadata is unavailable, the workflow lets the runtime/provider path handle the failure.

Put scoring or evaluation policy in `instructions`. `evaluationPrompt` is the user prompt. Agent Kit forwards those channels separately and does not invent a default system instruction.

`strictStructuredOutput` defaults to `false` and is forwarded unchanged to `ExecutionRequest`. Set it to `true` when the evaluation schema must be emitted as Laravel AI strict structured output.

The result keeps both `structuredOutput` and raw `output` so consumers can distinguish empty, malformed, and valid structured payloads.

Empty transcripts are rejected by default. Set `allowEmptyTranscript: true` when the schema should classify empty or malformed audio itself, for example language-test scoring workflows that map empty audio to a low score.

`EvaluationImageInput` supports URL, base64, local path, storage, and upload variants. The Laravel AI SDK image attachment objects are created inside Agent Kit bridge code; application code should use the package-owned DTOs.

### Media input trust boundaries

The same trust model as transcription sources applies to `EvaluationImageInput`:

- Prefer `fromUpload()`, `fromBase64()`, or `fromStorage()` for user-supplied content.
- Use `fromPath()` only for trusted local paths controlled by your application.
- Use `fromUrl()` only for approved remote assets; configure `media_input.url_allowed_hosts` when URLs may be user-influenced.

See [Streaming and modalities](streaming-and-modalities.md#media-input-trust-boundaries) for the full table and SSRF guidance.

### Pipeline usage

The blueprint can also be used in package pipelines through `AudioImageStructuredEvaluationPipelineStep` or `AudioImageStructuredEvaluationPipeline`.

~~~php
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioImageStructuredEvaluationPipeline;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;

$context = new RunContext(
    runId: 'language-score-001',
    input: [
        'audio_image_structured_evaluation_request' => $request,
    ],
);
~~~

Queued pipeline payloads should pass only Agent Kit DTOs and scalar metadata. Do not serialize Laravel AI SDK file objects directly.

## Prompt requirements

Register the prompt templates referenced by blueprint request fields before execution. Use prompt versions deliberately so workflow behavior is reproducible.

See [Prompts](prompts.md).

## Facade shortcuts

The `AgentKit` facade provides concise shortcuts:

~~~php
AgentKit::evaluateText($request);
AgentKit::evaluateAudio($request);
AgentKit::evaluateAudioImage($request);
~~~

Prefer dependency injection for long-lived services and jobs so dependencies remain explicit and easy to fake in tests.

## Testing blueprints

Use package fakes to test application behavior without live provider calls. See [Testing](testing.md).
