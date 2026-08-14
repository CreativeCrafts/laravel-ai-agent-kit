<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience;

use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Resilience\CostEstimate;

interface CostEstimator
{
    public function estimate(ExecutionRequest $request): ?CostEstimate;
}
