<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\AudioGenerationRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\EmbeddingsRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\ImageGenerationRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\RerankingRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\TranscriptionRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderTargetResolver;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\AudioGenerationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\EmbeddingsRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\ImageGenerationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\RerankingRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderTargetResolver;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\DefaultProviderSelector;
use Illuminate\Support\Facades\Config;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\Audio;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Image;
use Laravel\Ai\Reranking;
use Laravel\Ai\Responses\Data\RankedDocument;
use Laravel\Ai\Transcription;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\SdkAudioGenerationRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\SdkEmbeddingsRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\SdkImageGenerationRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\SdkRerankingRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\SdkTranscriptionRuntime;

beforeEach(function (): void {
    app()->register(AiServiceProvider::class);

    /** @var array<string, mixed> $ai */
    $ai = require __DIR__ . '/../vendor/laravel/ai/config/ai.php';
    Config::set('ai', $ai);
    Config::set('ai.default', 'openai-test');
    Config::set('ai.default_for_transcription', 'openai-test');
    Config::set('ai.default_for_embeddings', 'openai-test');
    Config::set('ai.default_for_images', 'openai-test');
    Config::set('ai.default_for_reranking', 'cohere-test');
    Config::set('ai.default_for_audio', 'openai-test');
    Config::set('ai.providers', [
      'openai-test' => [
        'driver' => 'openai',
        'key' => 'test-key-for-ci',
      ],
      'cohere-test' => [
        'driver' => 'cohere',
        'key' => 'test-key-for-ci',
      ],
    ]);
});

it('resolves transcription provider profiles before invoking laravel ai', function (): void {
    configureModalityProfile('scorer-transcription', 'openai', 'openai-test', 'gpt-test-transcribe', ['audio_transcription']);
    $captured = captureModalityProvider();

    Transcription::fake(function ($prompt) use ($captured): string {
        $captured($prompt);

        return 'profile transcript';
    })->preventStrayTranscriptions();

    forgetModalityRuntimes();

    $result = app(TranscriptionRuntime::class)->transcribe(
        new TranscriptionRequest(
            runId: 'ak-16',
            base64Audio: base64_encode('fake-audio-bytes'),
            mimeType: 'audio/wav',
            provider: 'scorer-transcription',
        ),
    );

    expect($result->transcript)->toBe('profile transcript')
      ->and($captured->sdkProvider)->toBe('openai-test')
      ->and($captured->model)->toBe('gpt-test-transcribe');
});

it('resolves embedding provider profiles before invoking laravel ai', function (): void {
    configureModalityProfile('scorer-embeddings', 'openai', 'openai-test', 'gpt-test-embed', ['embeddings']);
    $captured = captureModalityProvider();

    Embeddings::fake(function ($prompt) use ($captured): array {
        $captured($prompt);

        return [[0.1, 0.2]];
    })->preventStrayEmbeddings();

    forgetModalityRuntimes();

    $result = app(EmbeddingsRuntime::class)->embed(
        new EmbeddingsRequest(
            runId: 'ak-17',
            inputs: ['hello'],
            provider: 'scorer-embeddings',
        ),
    );

    expect($result->vectors)->toHaveCount(1)
      ->and($captured->sdkProvider)->toBe('openai-test')
      ->and($captured->model)->toBe('gpt-test-embed');
});

it('resolves image generation provider profiles before invoking laravel ai', function (): void {
    configureModalityProfile('scorer-image', 'openai', 'openai-test', 'gpt-test-image', ['image_generation']);
    $captured = captureModalityProvider();

    Image::fake(function ($prompt) use ($captured): string {
        $captured($prompt);

        return 'fake-b64-image';
    })->preventStrayImages();

    forgetModalityRuntimes();

    $result = app(ImageGenerationRuntime::class)->generate(
        new ImageGenerationRequest(
            runId: 'ak-18',
            prompt: 'A circle.',
            provider: 'scorer-image',
        ),
    );

    expect($result->imageBase64)->toBe('fake-b64-image')
      ->and($captured->sdkProvider)->toBe('openai-test')
      ->and($captured->model)->toBe('gpt-test-image');
});

it('resolves audio generation provider profiles before invoking laravel ai', function (): void {
    configureModalityProfile('scorer-audio', 'openai', 'openai-test', 'gpt-test-audio', ['audio_generation']);
    $captured = captureModalityProvider();

    Audio::fake(function ($prompt) use ($captured): string {
        $captured($prompt);

        return base64_encode('fake-tts-bytes');
    })->preventStrayAudio();

    forgetModalityRuntimes();

    $result = app(AudioGenerationRuntime::class)->generate(
        new AudioGenerationRequest(
            runId: 'ak-19',
            text: 'Hello.',
            provider: 'scorer-audio',
        ),
    );

    expect(base64_decode($result->audioBase64, true))->toBe('fake-tts-bytes')
      ->and($captured->sdkProvider)->toBe('openai-test')
      ->and($captured->model)->toBe('gpt-test-audio');
});

it('resolves reranking provider profiles before invoking laravel ai', function (): void {
    configureModalityProfile('scorer-rerank', 'cohere', 'cohere-test', 'rerank-test', ['reranking']);
    $captured = captureModalityProvider();

    Reranking::fake(function ($prompt) use ($captured): array {
        $captured($prompt);

        return [new RankedDocument(0, 'first doc', 0.9)];
    })->preventStrayRerankings();

    forgetModalityRuntimes();

    $result = app(RerankingRuntime::class)->rerank(
        new RerankingRequest(
            runId: 'ak-20',
            documents: ['first doc'],
            query: 'find',
            provider: 'scorer-rerank',
        ),
    );

    expect($result->documents[0]->document)->toBe('first doc')
      ->and($captured->sdkProvider)->toBe('cohere-test')
      ->and($captured->model)->toBe('rerank-test');
});

/**
 * @param list<string> $capabilities
 */
function configureModalityProfile(
    string $profile,
    string $driver,
    string $sdkProvider,
    string $model,
    array $capabilities,
): void {
    config()->set("ai-agent-kit.providers.{$profile}", [
      'driver' => $driver,
      'sdk_provider' => $sdkProvider,
      'enabled' => true,
      'capabilities' => $capabilities,
      'options' => ['model' => $model],
    ]);

    app()->forgetInstance(ConfiguredProviderRegistry::class);
    app()->forgetInstance(ProviderRegistry::class);
    app()->forgetInstance(DefaultProviderSelector::class);
    app()->forgetInstance(ProviderSelector::class);
    app()->forgetInstance(ConfiguredProviderTargetResolver::class);
    app()->forgetInstance(ProviderTargetResolver::class);
}

function forgetModalityRuntimes(): void
{
    app()->forgetInstance(TranscriptionRuntime::class);
    app()->forgetInstance(EmbeddingsRuntime::class);
    app()->forgetInstance(ImageGenerationRuntime::class);
    app()->forgetInstance(AudioGenerationRuntime::class);
    app()->forgetInstance(RerankingRuntime::class);
    app()->forgetInstance(SdkTranscriptionRuntime::class);
    app()->forgetInstance(SdkEmbeddingsRuntime::class);
    app()->forgetInstance(SdkImageGenerationRuntime::class);
    app()->forgetInstance(SdkAudioGenerationRuntime::class);
    app()->forgetInstance(SdkRerankingRuntime::class);
}

function captureModalityProvider(): object
{
    return new class () {
        public ?string $sdkProvider = null;

        public ?string $model = null;

        public function __invoke(object $prompt): void
        {
            $this->sdkProvider = modalityPromptString($prompt, ['provider', 'providerName']);
            $this->model = modalityPromptString($prompt, ['model']);

            if (isset($prompt->provider) && is_object($prompt->provider) && method_exists($prompt->provider, 'name')) {
                $this->sdkProvider = $prompt->provider->name();
            }
        }
    };
}

/**
 * @param list<string> $properties
 */
function modalityPromptString(object $prompt, array $properties): ?string
{
    foreach ($properties as $property) {
        if (!isset($prompt->{$property})) {
            continue;
        }

        $value = $prompt->{$property};

        if (is_string($value) && $value !== '') {
            return $value;
        }
    }

    return null;
}
