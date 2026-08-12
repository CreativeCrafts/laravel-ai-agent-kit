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

final class TestAudioImageEvaluationSchema implements HasStructuredOutput
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'level' => $schema->string(),
        ];
    }
}
