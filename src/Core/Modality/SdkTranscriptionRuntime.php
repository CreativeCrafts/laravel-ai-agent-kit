<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\TranscriptionRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\Exceptions\UnsupportedTranscriptionAudioSourceException;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\Exceptions\UnsupportedTranscriptionPromptException;
use Illuminate\Http\UploadedFile;
use Laravel\Ai\PendingResponses\PendingTranscriptionGeneration;
use Laravel\Ai\Responses\Data\TranscriptionSegment;
use Laravel\Ai\Transcription;
use ReflectionMethod;

final readonly class SdkTranscriptionRuntime implements TranscriptionRuntime
{
    public function transcribe(TranscriptionRequest $request): TranscriptionResult
    {
        $source = $request->resolvedAudioSource();
        $pending = $this->pendingFromSource($source);
        $providerOptions = [];

        if ($request->prompt !== null) {
            $providerOptions['prompt'] = $request->prompt;
        }

        if ($request->providerOptions instanceof TranscriptionProviderOptions) {
            $providerOptions = array_merge($providerOptions, $request->providerOptions->toProviderOptions());
        }

        if ($providerOptions !== []) {
            if (!method_exists($pending, 'providerOptions')) {
                throw UnsupportedTranscriptionPromptException::forInstalledSdk();
            }

            $providerOptionsMethod = new ReflectionMethod($pending, 'providerOptions');
            $providerOptionsMethod->invoke($pending, $providerOptions);
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

        $response = $pending->generate($request->provider, $request->model);

        $segments = [];

        foreach ($response->segments as $segment) {
            if ($segment instanceof TranscriptionSegment) {
                $segments[] = new TranscriptionSegmentResult(
                    text: $segment->text,
                    speaker: $segment->speaker,
                    startSeconds: $segment->startSeconds,
                    endSeconds: $segment->endSeconds,
                );
            }
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
            TranscriptionAudioSourceKind::Storage => Transcription::fromStorage((string) $payload, $source->disk()),
            TranscriptionAudioSourceKind::Upload => Transcription::fromUpload($this->uploadedFilePayload($source, $payload)),
            TranscriptionAudioSourceKind::Url => throw UnsupportedTranscriptionAudioSourceException::forSourceKind($source->kind()),
        };
    }

    private function uploadedFilePayload(TranscriptionAudioSource $source, mixed $payload): UploadedFile
    {
        if ($payload instanceof UploadedFile) {
            return $payload;
        }

        throw UnsupportedTranscriptionAudioSourceException::forSourceKind($source->kind());
    }
}
