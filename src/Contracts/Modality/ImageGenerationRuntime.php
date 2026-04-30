<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Modality;

use CreativeCrafts\LaravelAiAgentKit\Core\Modality\ImageGenerationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\ImageGenerationResult;

interface ImageGenerationRuntime
{
    public function generate(ImageGenerationRequest $request): ImageGenerationResult;
}
