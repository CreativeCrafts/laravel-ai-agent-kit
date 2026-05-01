<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\AudioGenerationRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\EmbeddingsRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\ImageGenerationRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\RerankingRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\TranscriptionRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\AudioGenerationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\AudioGenerationResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\EmbeddingsRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\ImageGenerationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\RerankingRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionResult;
use Illuminate\Support\Facades\Config;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\Audio;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Image;
use Laravel\Ai\Reranking;
use Laravel\Ai\Responses\Data\RankedDocument;
use Laravel\Ai\Transcription;

beforeEach(function (): void {
    app()->register(AiServiceProvider::class);

    /** @var array<string, mixed> $ai */
    $ai = require __DIR__.'/../vendor/laravel/ai/config/ai.php';
    Config::set('ai', $ai);
    Config::set('ai.default', 'openai');
    Config::set('ai.default_for_transcription', 'openai');
    Config::set('ai.default_for_embeddings', 'openai');
    Config::set('ai.default_for_images', 'openai');
    Config::set('ai.default_for_reranking', 'cohere');
    Config::set('ai.default_for_audio', 'openai');
    Config::set('ai.providers', [
        'openai' => [
            'driver' => 'openai',
            'key' => 'test-key-for-ci',
        ],
        'cohere' => [
            'driver' => 'cohere',
            'key' => 'test-key-for-ci',
        ],
    ]);
});

it('transcribes base64 audio through the sdk transcription runtime', function (): void {
    Transcription::fake(['hello modality'])->preventStrayTranscriptions();

    app()->forgetInstance(TranscriptionRuntime::class);

    /** @var TranscriptionRuntime $runtime */
    $runtime = app(TranscriptionRuntime::class);

    $audio = base64_encode('fake-audio-bytes');
    $result = $runtime->transcribe(
        new TranscriptionRequest(
            runId: 'mod-tx-1',
            base64Audio: $audio,
            mimeType: 'audio/wav',
            provider: 'openai',
            model: 'whisper-1',
        ),
    );

    expect($result->transcript)->toBe('hello modality')
        ->and($result->runId)->toBe('mod-tx-1')
        ->and($result->provider)->not->toBe('');
});

it('preserves embeddings batch input order', function (): void {
    Embeddings::fake([
        [
            [1.0, 0.0],
            [0.0, 1.0],
            [0.5, 0.5],
        ],
    ])->preventStrayEmbeddings();

    app()->forgetInstance(EmbeddingsRuntime::class);

    /** @var EmbeddingsRuntime $runtime */
    $runtime = app(EmbeddingsRuntime::class);

    $result = $runtime->embed(
        new EmbeddingsRequest(
            runId: 'mod-emb-1',
            inputs: ['first', 'second', 'third'],
            dimensions: 2,
            provider: 'openai',
            model: 'text-embedding-3-small',
        ),
    );

    expect($result->vectors)->toHaveCount(3)
        ->and($result->vectors[0][0])->toBe(1.0)
        ->and($result->vectors[1][1])->toBe(1.0)
        ->and($result->vectors[2][0])->toBe(0.5);
});

it('generates an image through the sdk image runtime', function (): void {
    Image::fake(['fake-b64-image'])->preventStrayImages();

    app()->forgetInstance(ImageGenerationRuntime::class);

    /** @var ImageGenerationRuntime $runtime */
    $runtime = app(ImageGenerationRuntime::class);

    $result = $runtime->generate(
        new ImageGenerationRequest(
            runId: 'mod-img-1',
            prompt: 'A red circle on white.',
            provider: 'openai',
            model: 'dall-e-3',
        ),
    );

    expect($result->imageBase64)->toBe('fake-b64-image')
        ->and($result->runId)->toBe('mod-img-1')
        ->and($result->imageCount)->toBeGreaterThan(0);
});

it('reranks documents through the sdk reranking runtime', function (): void {
    Reranking::fake([
        [
            new RankedDocument(2, 'third doc', 0.95),
            new RankedDocument(0, 'first doc', 0.85),
            new RankedDocument(1, 'second doc', 0.75),
        ],
    ])->preventStrayRerankings();

    app()->forgetInstance(RerankingRuntime::class);

    /** @var RerankingRuntime $runtime */
    $runtime = app(RerankingRuntime::class);

    $result = $runtime->rerank(
        new RerankingRequest(
            runId: 'mod-rr-1',
            documents: ['first doc', 'second doc', 'third doc'],
            query: 'find the third',
            provider: 'cohere',
            model: 'rerank-model',
        ),
    );

    expect($result->documents[0]->originalIndex)->toBe(2)
        ->and($result->documents[0]->document)->toBe('third doc')
        ->and($result->documents[1]->originalIndex)->toBe(0);
});

it('generates audio through the sdk audio generation runtime', function (): void {
    Audio::fake([base64_encode('fake-tts-bytes')])->preventStrayAudio();

    app()->forgetInstance(AudioGenerationRuntime::class);

    /** @var AudioGenerationRuntime $runtime */
    $runtime = app(AudioGenerationRuntime::class);

    $result = $runtime->generate(
        new AudioGenerationRequest(
            runId: 'mod-audio-1',
            text: 'Hello from the test.',
            provider: 'openai',
            model: 'gpt-4o-mini-tts',
        ),
    );

    expect($result->runId)->toBe('mod-audio-1')
        ->and(base64_decode($result->audioBase64, true))->toBe('fake-tts-bytes')
        ->and($result->provider)->not->toBe('');
});

it('propagates audio generation failures when fakes require responses', function (): void {
    Audio::fake([])->preventStrayAudio();

    app()->forgetInstance(AudioGenerationRuntime::class);

    /** @var AudioGenerationRuntime $runtime */
    $runtime = app(AudioGenerationRuntime::class);

    $runtime->generate(
        new AudioGenerationRequest(
            runId: 'mod-audio-fail',
            text: 'This will fail under preventStrayAudio.',
            provider: 'openai',
            model: 'gpt-4o-mini-tts',
        ),
    );
})->throws(RuntimeException::class);

it('resolves a custom audio generation driver from config', function (): void {
    Config::set('ai-agent-kit.modalities.audio_generation.default_driver', TestStubAudioGenerationRuntime::class);

    app()->forgetInstance(AudioGenerationRuntime::class);

    $runtime = app(AudioGenerationRuntime::class);

    expect($runtime)->toBeInstanceOf(TestStubAudioGenerationRuntime::class);

    $result = $runtime->generate(
        new AudioGenerationRequest(runId: 'stub-audio-1', text: 'Hi'),
    );

    expect($result->audioBase64)->toBe('c3R1Yi1hdWRpbw==')
        ->and(base64_decode($result->audioBase64, true))->toBe('stub-audio');
});

it('resolves a custom transcription driver from config', function (): void {
    Config::set('ai-agent-kit.modalities.transcription.default_driver', TestStubTranscriptionRuntime::class);

    app()->forgetInstance(TranscriptionRuntime::class);

    $runtime = app(TranscriptionRuntime::class);

    expect($runtime)->toBeInstanceOf(TestStubTranscriptionRuntime::class);

    $result = $runtime->transcribe(
        new TranscriptionRequest(runId: 'stub-1', base64Audio: base64_encode('x')),
    );

    expect($result->transcript)->toBe('stub-transcript');
});

final class TestStubTranscriptionRuntime implements TranscriptionRuntime
{
    public function transcribe(TranscriptionRequest $request): TranscriptionResult
    {
        return new TranscriptionResult(
            runId: $request->runId,
            transcript: 'stub-transcript',
            provider: 'stub',
            model: 'stub',
            promptTokens: 0,
            completionTokens: 0,
        );
    }
}

final class TestStubAudioGenerationRuntime implements AudioGenerationRuntime
{
    public function generate(AudioGenerationRequest $request): AudioGenerationResult
    {
        return new AudioGenerationResult(
            runId: $request->runId,
            audioBase64: base64_encode('stub-audio'),
            mimeType: 'audio/mpeg',
            provider: 'stub',
            model: 'stub',
            metadata: $request->metadata,
        );
    }
}
