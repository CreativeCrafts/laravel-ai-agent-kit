<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Modality;

use CreativeCrafts\LaravelAiAgentKit\Core\Modality\EmbeddingsRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\EmbeddingsResult;

interface EmbeddingsRuntime
{
    public function embed(EmbeddingsRequest $request): EmbeddingsResult;
}
