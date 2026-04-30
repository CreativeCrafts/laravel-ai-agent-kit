<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Modality;

use CreativeCrafts\LaravelAiAgentKit\Core\Modality\RerankingRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\RerankingResult;

interface RerankingRuntime
{
    public function rerank(RerankingRequest $request): RerankingResult;
}
