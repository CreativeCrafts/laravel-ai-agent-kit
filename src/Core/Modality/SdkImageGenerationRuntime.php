<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\ImageGenerationRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderTargetResolver;
use InvalidArgumentException;
use Laravel\Ai\Image;

final readonly class SdkImageGenerationRuntime implements ImageGenerationRuntime
{
    public function __construct(
        private ProviderTargetResolver $providerTargetResolver,
    ) {
    }

    public function generate(ImageGenerationRequest $request): ImageGenerationResult
    {
        $pending = Image::of($request->prompt);
        $target = $this->providerTargetResolver->resolveExplicit($request->provider, $request->model);

        if ($request->size === '1:1') {
            $pending = $pending->size('1:1');
        } elseif ($request->size === '2:3') {
            $pending = $pending->size('2:3');
        } elseif ($request->size === '3:2') {
            $pending = $pending->size('3:2');
        } elseif ($request->size !== null) {
            throw new InvalidArgumentException('Invalid image size; use 1:1, 2:3, or 3:2.');
        }

        if ($request->quality === 'low') {
            $pending = $pending->quality('low');
        } elseif ($request->quality === 'medium') {
            $pending = $pending->quality('medium');
        } elseif ($request->quality === 'high') {
            $pending = $pending->quality('high');
        } elseif ($request->quality !== null) {
            throw new InvalidArgumentException('Invalid image quality; use low, medium, or high.');
        }

        if ($request->timeout !== null) {
            $pending = $pending->timeout($request->timeout);
        }

        $response = $pending->generate($target->sdkProviderName, $target->model);

        $first = $response->firstImage();
        $provider = $response->meta->provider ?? 'unknown';
        $model = $response->meta->model ?? 'unknown';

        return new ImageGenerationResult(
            runId: $request->runId,
            imageBase64: $first->image,
            mimeType: $first->mime,
            provider: $provider,
            model: $model,
            imageCount: $response->count(),
            promptTokens: $response->usage->promptTokens ?? 0,
            completionTokens: $response->usage->completionTokens ?? 0,
            metadata: $request->metadata,
        );
    }
}
