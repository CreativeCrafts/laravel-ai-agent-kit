<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioImageStructuredEvaluation;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioImageStructuredEvaluationPipelineStep;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioImageStructuredEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\EvaluationImageInput;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Exceptions\AudioImageStructuredEvaluationException;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\TranscriptionRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionAudioSource;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionAudioSourceKind;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeTranscriptionRuntime;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Files\RemoteImage;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;

it('records source-backed transcription requests through the fake runtime', function (): void {
    $runtime = new FakeTranscriptionRuntime();

    $result = $runtime->transcribe(
        TranscriptionRequest::fromAudioSource(
            runId: 'tx-storage',
            audioSource: TranscriptionAudioSource::fromStorage('answers/audio.mp3', 's3-audios', 'audio/mpeg'),
            provider: 'openai',
            model: 'gpt-4o-transcribe',
        ),
    );

    $request = $runtime->lastRequest();

    expect($request)->toBeInstanceOf(TranscriptionRequest::class)
        ->and($request?->resolvedAudioSource()->kind())->toBe(TranscriptionAudioSourceKind::Storage)
        ->and($request?->resolvedAudioSource()->safeMetadata())->toMatchArray([
            'kind' => 'storage',
            'disk' => 's3-audios',
            'reference_basename' => 'audio.mp3',
            'reference_fingerprint' => hash('sha256', 'answers/audio.mp3'),
        ])
        ->and($result->metadata['audio_source'])->toMatchArray([
            'kind' => 'storage',
            'disk' => 's3-audios',
            'reference_basename' => 'audio.mp3',
            'reference_fingerprint' => hash('sha256', 'answers/audio.mp3'),
        ]);
});

it('evaluates audio and image with structured output through Agent Kit runtimes', function (): void {
    $transcriptions = new FakeTranscriptionRuntime([
        new TranscriptionResult(
            runId: 'score-1:transcription',
            transcript: 'Jag ser en person vid ett bord.',
            provider: 'openai',
            model: 'gpt-4o-transcribe',
            promptTokens: 3,
            completionTokens: 5,
            metadata: ['audio_source' => ['kind' => 'storage']],
        ),
    ]);

    $runtime = new FakeAiRuntime([
        new ExecutionResult(
            runId: 'score-1:evaluation',
            output: '{"level":"A2"}',
            provider: 'openai',
            model: 'gpt-4.1-mini',
            usage: [
                'prompt_tokens' => 11,
                'completion_tokens' => 7,
                'total_tokens' => 18,
            ],
            structuredOutput: ['level' => 'A2'],
        ),
    ]);

    app()->instance(TranscriptionRuntime::class, $transcriptions);
    app()->instance(AiRuntime::class, $runtime);

    $request = new AudioImageStructuredEvaluationRequest(
        runId: 'score-1',
        audio: TranscriptionAudioSource::fromStorage('answers/audio.mp3', 's3-audios', 'audio/mpeg'),
        image: EvaluationImageInput::fromUrl('https://example.com/image.jpg'),
        evaluationPrompt: 'Evaluate the transcript and image.',
        schema: TestAudioImageEvaluationSchema::class,
        instructions: ['Return strict JSON.'],
        transcriptionProvider: 'openai-transcription',
        transcriptionModel: 'gpt-4o-transcribe',
        evaluationProvider: 'openai-vision',
        evaluationModel: 'gpt-4.1-mini',
        allowEmptyTranscript: false,
    );

    config()->set('ai-agent-kit.providers', [
        'openai-transcription' => [
            'driver' => 'openai',
            'enabled' => true,
            'capabilities' => ['audio_transcription'],
        ],
        'openai-vision' => [
            'driver' => 'openai',
            'enabled' => true,
            'capabilities' => ['text_generation', 'structured_output', 'vision'],
        ],
    ]);

    $result = app(AudioImageStructuredEvaluation::class)->evaluate($request);

    expect($result->transcript)->toBe('Jag ser en person vid ett bord.')
        ->and($result->structuredOutput)->toBe(['level' => 'A2'])
        ->and($result->output)->toBe('{"level":"A2"}')
        ->and($result->usage['transcription_prompt_tokens'])->toBe(3)
        ->and($result->usage['evaluation_total_tokens'])->toBe(18)
        ->and($runtime->lastRequest()?->attachments[0])->toBeInstanceOf(RemoteImage::class)
        ->and($runtime->lastRequest()?->schema)->toBe(TestAudioImageEvaluationSchema::class)
        ->and($runtime->lastRequest()?->strictStructuredOutput)->toBeFalse()
        ->and($runtime->lastRequest()?->instructions)->toBe(['Return strict JSON.'])
        ->and($transcriptions->lastRequest()?->resolvedAudioSource()->kind())->toBe(TranscriptionAudioSourceKind::Storage);
});

it('forwards strict structured output from the audio-image request to the evaluation runtime', function (): void {
    app()->instance(TranscriptionRuntime::class, new FakeTranscriptionRuntime([
        new TranscriptionResult(
            runId: 'score-strict:transcription',
            transcript: 'A person sits at a table.',
            provider: 'openai',
            model: 'gpt-4o-transcribe',
            promptTokens: 1,
            completionTokens: 1,
        ),
    ]));

    $runtime = new FakeAiRuntime([
        new ExecutionResult(
            runId: 'score-strict:evaluation',
            output: '{"level":"A2"}',
            provider: 'openai',
            model: 'gpt-4.1-mini',
            structuredOutput: ['level' => 'A2'],
        ),
    ]);

    app()->instance(AiRuntime::class, $runtime);

    config()->set('ai-agent-kit.providers', [
        'openai-vision' => [
            'driver' => 'openai',
            'enabled' => true,
            'capabilities' => ['text_generation', 'structured_output', 'vision'],
        ],
    ]);

    app(AudioImageStructuredEvaluation::class)->evaluate(
        new AudioImageStructuredEvaluationRequest(
            runId: 'score-strict',
            audio: TranscriptionAudioSource::fromBase64(base64_encode('audio'), 'audio/wav'),
            image: EvaluationImageInput::fromUrl('https://example.com/image.jpg'),
            evaluationPrompt: 'Evaluate.',
            schema: TestAudioImageEvaluationSchema::class,
            evaluationProvider: 'openai-vision',
            strictStructuredOutput: true,
        ),
    );

    expect($runtime->lastRequest()?->strictStructuredOutput)->toBeTrue();
});

it('rejects empty transcripts by default and allows them when requested', function (): void {
    $runtime = new FakeAiRuntime([
        new ExecutionResult(
            runId: 'empty-allowed:evaluation',
            output: '{"level":"A1"}',
            provider: 'openai',
            model: 'gpt-4.1-mini',
            structuredOutput: ['level' => 'A1'],
        ),
    ]);

    app()->instance(AiRuntime::class, $runtime);

    app()->instance(TranscriptionRuntime::class, new FakeTranscriptionRuntime([
        new TranscriptionResult(
            runId: 'empty-rejected:transcription',
            transcript: '',
            provider: 'openai',
            model: 'gpt-4o-transcribe',
            promptTokens: 0,
            completionTokens: 0,
        ),
    ]));

    $baseRequest = new AudioImageStructuredEvaluationRequest(
        runId: 'empty-rejected',
        audio: TranscriptionAudioSource::fromBase64(base64_encode('audio'), 'audio/wav'),
        image: EvaluationImageInput::fromUrl('https://example.com/image.jpg'),
        evaluationPrompt: 'Evaluate.',
        schema: TestAudioImageEvaluationSchema::class,
    );

    expect(fn () => app(AudioImageStructuredEvaluation::class)->evaluate($baseRequest))
        ->toThrow(AudioImageStructuredEvaluationException::class);

    app()->instance(TranscriptionRuntime::class, new FakeTranscriptionRuntime([
        new TranscriptionResult(
            runId: 'empty-allowed:transcription',
            transcript: '',
            provider: 'openai',
            model: 'gpt-4o-transcribe',
            promptTokens: 0,
            completionTokens: 0,
        ),
    ]));

    $allowedRequest = new AudioImageStructuredEvaluationRequest(
        runId: 'empty-allowed',
        audio: TranscriptionAudioSource::fromBase64(base64_encode('audio'), 'audio/wav'),
        image: EvaluationImageInput::fromUrl('https://example.com/image.jpg'),
        evaluationPrompt: 'Evaluate.',
        schema: TestAudioImageEvaluationSchema::class,
        allowEmptyTranscript: true,
    );

    $result = app(AudioImageStructuredEvaluation::class)->evaluate($allowedRequest);

    expect($result->structuredOutput)->toBe(['level' => 'A1']);
});

it('fails closed when configured providers lack required capabilities', function (): void {
    config()->set('ai-agent-kit.providers', [
        'no-audio' => [
            'driver' => 'openai',
            'enabled' => true,
            'capabilities' => [],
        ],
        'no-image' => [
            'driver' => 'openai',
            'enabled' => true,
            'capabilities' => ['text_generation', 'structured_output'],
        ],
    ]);

    $request = new AudioImageStructuredEvaluationRequest(
        runId: 'capability-check',
        audio: TranscriptionAudioSource::fromBase64(base64_encode('audio'), 'audio/wav'),
        image: EvaluationImageInput::fromUrl('https://example.com/image.jpg'),
        evaluationPrompt: 'Evaluate.',
        schema: TestAudioImageEvaluationSchema::class,
        transcriptionProvider: 'no-audio',
        evaluationProvider: 'no-image',
    );

    expect(fn () => app(AudioImageStructuredEvaluation::class)->evaluate($request))
        ->toThrow(AudioImageStructuredEvaluationException::class, 'audio_transcription');

    $request = new AudioImageStructuredEvaluationRequest(
        runId: 'capability-check-image',
        audio: TranscriptionAudioSource::fromBase64(base64_encode('audio'), 'audio/wav'),
        image: EvaluationImageInput::fromUrl('https://example.com/image.jpg'),
        evaluationPrompt: 'Evaluate.',
        schema: TestAudioImageEvaluationSchema::class,
        evaluationProvider: 'no-image',
    );

    expect(fn () => app(AudioImageStructuredEvaluation::class)->evaluate($request))
        ->toThrow(AudioImageStructuredEvaluationException::class, 'image input capability');

    config()->set('ai-agent-kit.providers.no-text', [
        'driver' => 'openai',
        'enabled' => true,
        'capabilities' => ['structured_output', 'vision'],
    ]);

    $request = new AudioImageStructuredEvaluationRequest(
        runId: 'capability-check-text',
        audio: TranscriptionAudioSource::fromBase64(base64_encode('audio'), 'audio/wav'),
        image: EvaluationImageInput::fromUrl('https://example.com/image.jpg'),
        evaluationPrompt: 'Evaluate.',
        schema: TestAudioImageEvaluationSchema::class,
        evaluationProvider: 'no-text',
    );

    expect(fn () => app(AudioImageStructuredEvaluation::class)->evaluate($request))
        ->toThrow(AudioImageStructuredEvaluationException::class, 'text_generation');
});

it('stores results from the audio-image pipeline step in RunContext state', function (): void {
    $step = new AudioImageStructuredEvaluationPipelineStep(
        new AudioImageStructuredEvaluation(
            new FakeTranscriptionRuntime([
                new TranscriptionResult(
                    runId: 'pipe-1:transcription',
                    transcript: 'Pipeline transcript.',
                    provider: 'fake',
                    model: 'fake-transcribe',
                    promptTokens: 0,
                    completionTokens: 0,
                ),
            ]),
            new FakeAiRuntime([
                new ExecutionResult(
                    runId: 'pipe-1:evaluation',
                    output: '{"ok":true}',
                    provider: 'fake',
                    model: 'fake-eval',
                    structuredOutput: ['ok' => true],
                ),
            ]),
            app(ProviderRegistry::class),
        ),
    );

    $request = new AudioImageStructuredEvaluationRequest(
        runId: 'pipe-1',
        audio: TranscriptionAudioSource::fromBase64(base64_encode('audio'), 'audio/wav'),
        image: EvaluationImageInput::fromUrl('https://example.com/image.jpg'),
        evaluationPrompt: 'Evaluate.',
        schema: TestAudioImageEvaluationSchema::class,
        allowEmptyTranscript: true,
    );

    $context = $step->handle(new RunContext(
        runId: 'pipe-1',
        input: ['audio_image_structured_evaluation_request' => $request],
    ));

    expect($context->stepCount)->toBe(1)
        ->and($context->stateValue('audio_image_structured_evaluation_result')->structuredOutput)->toBe(['ok' => true]);
});

it('preserves the default evaluation prompt composition when no input template is supplied', function (): void {
    $runtime = bindAudioImageEvaluationRuntime(
        runId: 'default-prompt',
        transcript: 'A person sits at a table.',
    );

    app(AudioImageStructuredEvaluation::class)->evaluate(
        new AudioImageStructuredEvaluationRequest(
            runId: 'default-prompt',
            audio: TranscriptionAudioSource::fromBase64(base64_encode('audio'), 'audio/wav'),
            image: EvaluationImageInput::fromUrl('https://example.com/image.jpg'),
            evaluationPrompt: 'Evaluate.',
            schema: TestAudioImageEvaluationSchema::class,
        ),
    );

    expect($runtime->lastRequest()?->prompt)->toBe("Evaluate.\n\nTranscript:\nA person sits at a table.");
});

it('renders a JobMatch-style custom evaluation input template exactly', function (): void {
    $runtime = bindAudioImageEvaluationRuntime(
        runId: 'jobmatch-framing',
        transcript: 'Jag beskriver en bild.',
    );

    config()->set('ai-agent-kit.providers', [
        'openai-vision' => [
            'driver' => 'openai',
            'enabled' => true,
            'capabilities' => ['text_generation', 'structured_output', 'vision'],
        ],
    ]);

    app(AudioImageStructuredEvaluation::class)->evaluate(
        new AudioImageStructuredEvaluationRequest(
            runId: 'jobmatch-framing',
            audio: TranscriptionAudioSource::fromBase64(base64_encode('audio'), 'audio/wav'),
            image: EvaluationImageInput::fromUrl('https://example.com/image.jpg'),
            evaluationPrompt: '',
            schema: TestAudioImageEvaluationSchema::class,
            instructions: ['Score the spoken description against the image.'],
            evaluationProvider: 'openai-vision',
            evaluationModel: 'gpt-4.1-mini',
            strictStructuredOutput: true,
            evaluationInputTemplate: 'Transcribed Audio Text: "{{transcript}}"',
        ),
    );

    $evaluationRequest = $runtime->lastRequest();

    expect($evaluationRequest?->prompt)->toBe('Transcribed Audio Text: "Jag beskriver en bild."')
        ->and($evaluationRequest?->instructions)->toBe(['Score the spoken description against the image.'])
        ->and($evaluationRequest?->attachments)->toHaveCount(1)
        ->and($evaluationRequest?->attachments[0])->toBeInstanceOf(RemoteImage::class)
        ->and($evaluationRequest?->schema)->toBe(TestAudioImageEvaluationSchema::class)
        ->and($evaluationRequest?->provider)->toBe('openai-vision')
        ->and($evaluationRequest?->model)->toBe('gpt-4.1-mini')
        ->and($evaluationRequest?->strictStructuredOutput)->toBeTrue();
});

it('renders both supported evaluation input placeholders exactly', function (): void {
    $runtime = bindAudioImageEvaluationRuntime(
        runId: 'both-placeholders',
        transcript: 'Evidence text.',
    );

    app(AudioImageStructuredEvaluation::class)->evaluate(
        new AudioImageStructuredEvaluationRequest(
            runId: 'both-placeholders',
            audio: TranscriptionAudioSource::fromBase64(base64_encode('audio'), 'audio/wav'),
            image: EvaluationImageInput::fromUrl('https://example.com/image.jpg'),
            evaluationPrompt: 'Evaluate the evidence.',
            schema: TestAudioImageEvaluationSchema::class,
            evaluationInputTemplate: "{{evaluation_prompt}}\n\nEvidence: {{transcript}}",
        ),
    );

    expect($runtime->lastRequest()?->prompt)->toBe("Evaluate the evidence.\n\nEvidence: Evidence text.");
});

it('allows an empty evaluation prompt when the custom template omits it', function (): void {
    $runtime = bindAudioImageEvaluationRuntime(
        runId: 'omit-prompt',
        transcript: 'Only the transcript.',
    );

    $request = new AudioImageStructuredEvaluationRequest(
        runId: 'omit-prompt',
        audio: TranscriptionAudioSource::fromBase64(base64_encode('audio'), 'audio/wav'),
        image: EvaluationImageInput::fromUrl('https://example.com/image.jpg'),
        evaluationPrompt: '',
        schema: TestAudioImageEvaluationSchema::class,
        evaluationInputTemplate: '{{transcript}}',
    );

    app(AudioImageStructuredEvaluation::class)->evaluate($request);

    expect($request->evaluationPrompt)->toBe('')
        ->and($runtime->lastRequest()?->prompt)->toBe('Only the transcript.');
});

it('still requires a non-empty evaluation prompt in default mode', function (): void {
    expect(fn () => new AudioImageStructuredEvaluationRequest(
        runId: 'missing-prompt',
        audio: TranscriptionAudioSource::fromBase64(base64_encode('audio'), 'audio/wav'),
        image: EvaluationImageInput::fromUrl('https://example.com/image.jpg'),
        evaluationPrompt: '',
        schema: TestAudioImageEvaluationSchema::class,
    ))->toThrow(
        InvalidArgumentException::class,
        'Audio-image structured evaluation requests require a non-empty evaluation prompt.',
    );
});

it('requires a non-empty evaluation prompt when the template references it', function (): void {
    expect(fn () => new AudioImageStructuredEvaluationRequest(
        runId: 'required-prompt-placeholder',
        audio: TranscriptionAudioSource::fromBase64(base64_encode('audio'), 'audio/wav'),
        image: EvaluationImageInput::fromUrl('https://example.com/image.jpg'),
        evaluationPrompt: '',
        schema: TestAudioImageEvaluationSchema::class,
        evaluationInputTemplate: '{{evaluation_prompt}} -- {{transcript}}',
    ))->toThrow(
        InvalidArgumentException::class,
        'Audio-image structured evaluation requests require a non-empty evaluation prompt when the input template contains {{evaluation_prompt}}.',
    );
});

it('rejects a custom evaluation input template that omits the transcript placeholder', function (): void {
    $runtime = bindAudioImageEvaluationRuntime(runId: 'missing-transcript-placeholder', transcript: 'unused');

    expect(fn () => new AudioImageStructuredEvaluationRequest(
        runId: 'missing-transcript-placeholder',
        audio: TranscriptionAudioSource::fromBase64(base64_encode('audio'), 'audio/wav'),
        image: EvaluationImageInput::fromUrl('https://example.com/image.jpg'),
        evaluationPrompt: 'Evaluate.',
        schema: TestAudioImageEvaluationSchema::class,
        evaluationInputTemplate: '{{evaluation_prompt}} only',
    ))->toThrow(
        InvalidArgumentException::class,
        'Audio-image structured evaluation input template must contain {{transcript}}.',
    );

    expect($runtime->requests())->toBe([]);
});

it('rejects unknown evaluation input placeholders with a diagnostic', function (): void {
    expect(fn () => new AudioImageStructuredEvaluationRequest(
        runId: 'unknown-placeholder',
        audio: TranscriptionAudioSource::fromBase64(base64_encode('audio'), 'audio/wav'),
        image: EvaluationImageInput::fromUrl('https://example.com/image.jpg'),
        evaluationPrompt: 'Evaluate.',
        schema: TestAudioImageEvaluationSchema::class,
        evaluationInputTemplate: '{{transcript}} {{image_caption}}',
    ))->toThrow(
        InvalidArgumentException::class,
        'Audio-image structured evaluation input template contains unsupported placeholder {{image_caption}}. Supported placeholders are {{evaluation_prompt}} and {{transcript}}.',
    );
});

it('rejects whitespace and camelCase placeholder typos', function (string $template): void {
    expect(fn () => new AudioImageStructuredEvaluationRequest(
        runId: 'placeholder-typo',
        audio: TranscriptionAudioSource::fromBase64(base64_encode('audio'), 'audio/wav'),
        image: EvaluationImageInput::fromUrl('https://example.com/image.jpg'),
        evaluationPrompt: 'Evaluate.',
        schema: TestAudioImageEvaluationSchema::class,
        evaluationInputTemplate: $template,
    ))->toThrow(InvalidArgumentException::class);
})->with([
    'whitespace transcript placeholder' => ['{{ transcript }}'],
    'whitespace transcript sibling' => ['{{transcript}} {{ transcript }}'],
    'camelCase evaluation prompt' => ['{{transcript}} {{evaluationPrompt}}'],
]);

it('preserves quotes, newlines, and transcript whitespace in custom templates', function (): void {
    $transcript = "  Jag sa \"hej\".\nSedan gick jag hem.\r\n  ";
    $runtime = bindAudioImageEvaluationRuntime(
        runId: 'literal-bytes',
        transcript: $transcript,
    );

    app(AudioImageStructuredEvaluation::class)->evaluate(
        new AudioImageStructuredEvaluationRequest(
            runId: 'literal-bytes',
            audio: TranscriptionAudioSource::fromBase64(base64_encode('audio'), 'audio/wav'),
            image: EvaluationImageInput::fromUrl('https://example.com/image.jpg'),
            evaluationPrompt: '',
            schema: TestAudioImageEvaluationSchema::class,
            evaluationInputTemplate: "Transcribed Audio Text: \"{{transcript}}\"",
        ),
    );

    expect($runtime->lastRequest()?->prompt)->toBe("Transcribed Audio Text: \"  Jag sa \"hej\".\nSedan gick jag hem.\r\n  \"");
});

it('does not recursively interpret placeholder-looking transcript text', function (): void {
    $runtime = bindAudioImageEvaluationRuntime(
        runId: 'no-recursion',
        transcript: 'literal {{evaluation_prompt}} and {{transcript}}',
    );

    app(AudioImageStructuredEvaluation::class)->evaluate(
        new AudioImageStructuredEvaluationRequest(
            runId: 'no-recursion',
            audio: TranscriptionAudioSource::fromBase64(base64_encode('audio'), 'audio/wav'),
            image: EvaluationImageInput::fromUrl('https://example.com/image.jpg'),
            evaluationPrompt: 'Keep policy here.',
            schema: TestAudioImageEvaluationSchema::class,
            evaluationInputTemplate: 'Input={{transcript}}; Prompt={{evaluation_prompt}}',
        ),
    );

    expect($runtime->lastRequest()?->prompt)->toBe(
        'Input=literal {{evaluation_prompt}} and {{transcript}}; Prompt=Keep policy here.',
    );
});

it('renders an empty transcript through a custom template when allowed', function (): void {
    $runtime = bindAudioImageEvaluationRuntime(
        runId: 'empty-allowed-template',
        transcript: '',
    );

    app(AudioImageStructuredEvaluation::class)->evaluate(
        new AudioImageStructuredEvaluationRequest(
            runId: 'empty-allowed-template',
            audio: TranscriptionAudioSource::fromBase64(base64_encode('audio'), 'audio/wav'),
            image: EvaluationImageInput::fromUrl('https://example.com/image.jpg'),
            evaluationPrompt: '',
            schema: TestAudioImageEvaluationSchema::class,
            allowEmptyTranscript: true,
            evaluationInputTemplate: 'Transcribed Audio Text: "{{transcript}}"',
        ),
    );

    expect($runtime->lastRequest()?->prompt)->toBe('Transcribed Audio Text: ""');
});

it('rejects empty transcripts before evaluation even when a custom template is supplied', function (): void {
    $runtime = bindAudioImageEvaluationRuntime(
        runId: 'empty-rejected-template',
        transcript: '',
    );

    expect(fn () => app(AudioImageStructuredEvaluation::class)->evaluate(
        new AudioImageStructuredEvaluationRequest(
            runId: 'empty-rejected-template',
            audio: TranscriptionAudioSource::fromBase64(base64_encode('audio'), 'audio/wav'),
            image: EvaluationImageInput::fromUrl('https://example.com/image.jpg'),
            evaluationPrompt: '',
            schema: TestAudioImageEvaluationSchema::class,
            allowEmptyTranscript: false,
            evaluationInputTemplate: 'Transcribed Audio Text: "{{transcript}}"',
        ),
    ))->toThrow(AudioImageStructuredEvaluationException::class);

    expect($runtime->requests())->toBe([]);
});

it('maps pre-patch positional constructor arguments to the same fields', function (): void {
    $request = new AudioImageStructuredEvaluationRequest(
        'positional-compat',
        TranscriptionAudioSource::fromBase64(base64_encode('audio'), 'audio/wav'),
        EvaluationImageInput::fromUrl('https://example.com/image.jpg'),
        'Evaluate.',
        TestAudioImageEvaluationSchema::class,
        ['Keep policy in instructions.'],
        null,
        null,
        null,
        'openai-vision',
        'gpt-4.1-mini',
        null,
        null,
        null,
        false,
        null,
        null,
        false,
        [],
        true,
    );

    expect($request->runId)->toBe('positional-compat')
        ->and($request->evaluationPrompt)->toBe('Evaluate.')
        ->and($request->instructions)->toBe(['Keep policy in instructions.'])
        ->and($request->evaluationProvider)->toBe('openai-vision')
        ->and($request->evaluationModel)->toBe('gpt-4.1-mini')
        ->and($request->strictStructuredOutput)->toBeTrue()
        ->and($request->evaluationInputTemplate)->toBeNull();
});

it('renders multiple transcript placeholders by literal substitution', function (): void {
    $runtime = bindAudioImageEvaluationRuntime(
        runId: 'repeated-transcript',
        transcript: 'said once',
    );

    app(AudioImageStructuredEvaluation::class)->evaluate(
        new AudioImageStructuredEvaluationRequest(
            runId: 'repeated-transcript',
            audio: TranscriptionAudioSource::fromBase64(base64_encode('audio'), 'audio/wav'),
            image: EvaluationImageInput::fromUrl('https://example.com/image.jpg'),
            evaluationPrompt: '',
            schema: TestAudioImageEvaluationSchema::class,
            evaluationInputTemplate: 'First={{transcript}}; Second={{transcript}}',
        ),
    );

    expect($runtime->lastRequest()?->prompt)->toBe('First=said once; Second=said once');
});

function bindAudioImageEvaluationRuntime(string $runId, string $transcript): FakeAiRuntime
{
    app()->instance(TranscriptionRuntime::class, new FakeTranscriptionRuntime([
        new TranscriptionResult(
            runId: $runId . ':transcription',
            transcript: $transcript,
            provider: 'openai',
            model: 'gpt-4o-transcribe',
            promptTokens: 1,
            completionTokens: 1,
        ),
    ]));

    $runtime = new FakeAiRuntime([
        new ExecutionResult(
            runId: $runId . ':evaluation',
            output: '{"level":"A2"}',
            provider: 'openai',
            model: 'gpt-4.1-mini',
            structuredOutput: ['level' => 'A2'],
        ),
    ]);

    app()->instance(AiRuntime::class, $runtime);

    return $runtime;
}

final class TestAudioImageEvaluationSchema implements HasStructuredOutput
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'level' => $schema->string(),
        ];
    }
}
