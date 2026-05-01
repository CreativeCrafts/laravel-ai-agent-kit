<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\AudioGenerationRuntime;
use Laravel\Ai\Audio;

final readonly class SdkAudioGenerationRuntime implements AudioGenerationRuntime
{
    public function generate(AudioGenerationRequest $request): AudioGenerationResult
    {
        $pending = Audio::of($request->text);

        if ($request->voice !== null && $request->voice !== '') {
            $pending = $pending->voice($request->voice);
        } elseif ($request->maleVoice) {
            $pending = $pending->male();
        }

        if ($request->instructions !== null && $request->instructions !== '') {
            $pending = $pending->instructions($request->instructions);
        }

        if ($request->timeout !== null) {
            $pending = $pending->timeout($request->timeout);
        }

        $response = $pending->generate($request->provider, $request->model);

        $provider = $response->meta->provider ?? 'unknown';
        $model = $response->meta->model ?? 'unknown';

        return new AudioGenerationResult(
            runId: $request->runId,
            audioBase64: $response->audio,
            mimeType: $response->mimeType(),
            provider: $provider,
            model: $model,
            metadata: $request->metadata,
        );
    }
}
