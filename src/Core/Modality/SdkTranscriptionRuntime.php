<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\TranscriptionRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderTargetResolver;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\Exceptions\UnsupportedTranscriptionAudioSourceException;
use Illuminate\Http\UploadedFile;
use Laravel\Ai\Files\StoredAudio;
use Laravel\Ai\PendingResponses\PendingTranscriptionGeneration;
use Laravel\Ai\Transcription;

final readonly class SdkTranscriptionRuntime implements TranscriptionRuntime
{
    public function __construct(
        private ProviderTargetResolver $providerTargetResolver,
    ) {
    }

    public function transcribe(TranscriptionRequest $request): TranscriptionResult
    {
        $source = $request->resolvedAudioSource();
        $pending = $this->pendingFromSource($source);
        $target = $this->providerTargetResolver->resolveExplicit($request->provider, $request->model);
        $providerOptions = $target->providerOptions;

        if ($request->prompt !== null) {
            $providerOptions['prompt'] = $request->prompt;
        }

        if ($request->providerOptions instanceof TranscriptionProviderOptions) {
            $providerOptions = array_merge($providerOptions, $request->providerOptions->toProviderOptions());
        }

        if ($providerOptions !== []) {
            $pending = $pending->withProviderOptions($providerOptions);
        }

        if ($request->language !== null) {
            $pending = $pending->language($request->language);
        }

        if ($request->diarize) {
            $pending = $pending->diarize();
        }

        if ($request->timeout !== null) {
            $pending = $pending->timeout($request->timeout);
        }

        $response = $pending->generate($target->sdkProviderName, $target->model);

        $segments = [];

        foreach ($response->segments as $segment) {
            $segments[] = new TranscriptionSegmentResult(
                text: $segment->text,
                speaker: $segment->speaker,
                startSeconds: $segment->startSeconds,
                endSeconds: $segment->endSeconds,
            );
        }

        $provider = $response->meta->provider ?? 'unknown';
        $model = $response->meta->model ?? 'unknown';

        return new TranscriptionResult(
            runId: $request->runId,
            transcript: $response->text,
            provider: $provider,
            model: $model,
            promptTokens: $response->usage->promptTokens ?? 0,
            completionTokens: $response->usage->completionTokens ?? 0,
            segments: $segments,
            metadata: array_merge($request->metadata, [
                'audio_source' => $source->safeMetadata(),
            ]),
        );
    }

    private function pendingFromSource(TranscriptionAudioSource $source): PendingTranscriptionGeneration
    {
        $payload = $source->payload();

        return match ($source->kind()) {
            TranscriptionAudioSourceKind::Base64 => Transcription::fromBase64((string) $payload, $source->mimeType()),
            TranscriptionAudioSourceKind::Path => Transcription::fromPath((string) $payload, $source->mimeType()),
            TranscriptionAudioSourceKind::Storage => $this->pendingFromStorageSource($source, (string) $payload),
            TranscriptionAudioSourceKind::Upload => Transcription::fromUpload($this->uploadedFilePayload($source, $payload)),
            TranscriptionAudioSourceKind::Url => throw UnsupportedTranscriptionAudioSourceException::forSourceKind($source->kind()),
        };
    }

    private function pendingFromStorageSource(
        TranscriptionAudioSource $source,
        string $path,
    ): PendingTranscriptionGeneration {
        $audio = new StoredAudio($path, $source->disk());

        if ($source->mimeType() !== null) {
            $audio->withMimeType($source->mimeType());
        }

        return Transcription::of($audio);
    }

    private function uploadedFilePayload(TranscriptionAudioSource $source, mixed $payload): UploadedFile
    {
        if ($payload instanceof UploadedFile) {
            return $payload;
        }

        throw UnsupportedTranscriptionAudioSourceException::forSourceKind($source->kind());
    }
}
