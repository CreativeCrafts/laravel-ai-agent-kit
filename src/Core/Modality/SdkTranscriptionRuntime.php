<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\TranscriptionRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\Exceptions\UnsupportedTranscriptionPromptException;
use Laravel\Ai\PendingResponses\PendingTranscriptionGeneration;
use Laravel\Ai\Responses\Data\TranscriptionSegment;
use Laravel\Ai\Responses\TranscriptionResponse;
use Laravel\Ai\Transcription;
use ReflectionMethod;

final readonly class SdkTranscriptionRuntime implements TranscriptionRuntime
{
    public function transcribe(TranscriptionRequest $request): TranscriptionResult
    {
        /** @var PendingTranscriptionGeneration $pending */
        $pending = Transcription::fromBase64($request->base64Audio, $request->mimeType);
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

        /** @var TranscriptionResponse $response */
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
            metadata: $request->metadata,
        );
    }
}
