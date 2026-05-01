<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Modality;

use CreativeCrafts\LaravelAiAgentKit\Core\Modality\AudioGenerationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\AudioGenerationResult;

interface AudioGenerationRuntime
{
    public function generate(AudioGenerationRequest $request): AudioGenerationResult;
}
