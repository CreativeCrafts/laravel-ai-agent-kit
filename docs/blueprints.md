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

The transcription stage needs a provider profile with `audio_transcription`. The evaluation stage needs a provider profile with `structured_output`.

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

## Prompt requirements

Register the prompt templates referenced by blueprint request fields before execution. Use prompt versions deliberately so workflow behavior is reproducible.

See [Prompts](prompts.md).

## Facade shortcuts

The `AgentKit` facade provides concise shortcuts:

~~~php
AgentKit::evaluateText($request);
AgentKit::evaluateAudio($request);
~~~

Prefer dependency injection for long-lived services and jobs so dependencies remain explicit and easy to fake in tests.

## Testing blueprints

Use package fakes to test application behavior without live provider calls. See [Testing](testing.md).
