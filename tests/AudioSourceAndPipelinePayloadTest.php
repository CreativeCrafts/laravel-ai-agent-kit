<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioImageStructuredEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\EvaluationImageInput;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\Exceptions\UnsupportedTranscriptionAudioSourceException;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\SdkTranscriptionRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionAudioSource;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;

it('redacts raw base64 audio and upload contents from source metadata', function (): void {
    $raw = 'secret-audio-bytes';
    $base64 = base64_encode($raw);

    $metadata = TranscriptionAudioSource::fromBase64($base64, 'audio/wav')->safeMetadata();

    expect($metadata)
        ->toMatchArray([
            'kind' => 'base64',
            'mime_type' => 'audio/wav',
            'payload_length' => strlen($base64),
        ])
        ->and($metadata)->not->toHaveKey('reference')
        ->and(json_encode($metadata))->not->toContain($base64)
        ->and(json_encode($metadata))->not->toContain($raw);
});

it('fails closed for URL transcription sources before provider dispatch', function (): void {
    $runtime = new SdkTranscriptionRuntime();

    $runtime->transcribe(
        TranscriptionRequest::fromAudioSource(
            runId: 'url-source',
            audioSource: TranscriptionAudioSource::fromUrl('https://example.test/audio.mp3', 'audio/mpeg'),
        ),
    );
})->throws(UnsupportedTranscriptionAudioSourceException::class, 'url');

it('keeps queued audio-image workflow payloads in Agent Kit DTO terms', function (): void {
    $request = new AudioImageStructuredEvaluationRequest(
        runId: 'queued-audio-image-1',
        audio: TranscriptionAudioSource::fromStorage('answers/audio.mp3', 's3-audios', 'audio/mpeg'),
        image: EvaluationImageInput::fromUrl('https://example.test/question.jpg'),
        evaluationPrompt: 'Evaluate transcript and image.',
        schema: TestQueuedPayloadAudioImageSchema::class,
    );

    $context = new RunContext(
        runId: 'queued-audio-image-1',
        input: [
            'audio_image_structured_evaluation_request' => $request,
        ],
    );

    $serialized = serialize($context);

    expect($serialized)
        ->toContain(AudioImageStructuredEvaluationRequest::class)
        ->toContain(TranscriptionAudioSource::class)
        ->toContain(EvaluationImageInput::class)
        ->not->toContain('Laravel\\Ai\\Files\\')
        ->not->toContain('Laravel\\Ai\\Transcription');
});

final class TestQueuedPayloadAudioImageSchema implements HasStructuredOutput
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'ok' => $schema->boolean(),
        ];
    }
}
