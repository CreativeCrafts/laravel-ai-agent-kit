<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\AudioGenerationRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderTargetResolver;
use Laravel\Ai\Audio;

final readonly class SdkAudioGenerationRuntime implements AudioGenerationRuntime
{
    public function __construct(
        private ProviderTargetResolver $providerTargetResolver,
    ) {
    }

    public function generate(AudioGenerationRequest $request): AudioGenerationResult
    {
        $pending = Audio::of($request->text);
        $target = $this->providerTargetResolver->resolveExplicit($request->provider, $request->model);

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

        $response = $pending->generate($target->sdkProviderName, $target->model);

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
