<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Resilience;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience\CostEstimator;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\RuntimeBudgetExceededException;

final readonly class RequestMetadataCostEstimator implements CostEstimator
{
    public function estimate(ExecutionRequest $request): ?CostEstimate
    {
        $value = $request->metadata['cost_usd'] ?? $request->metadata['estimated_cost_usd'] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_int($value) && !is_float($value)) {
            throw RuntimeBudgetExceededException::forInvalidEstimatedCostType($request->runId, get_debug_type($value));
        }

        if ($value < 0) {
            throw RuntimeBudgetExceededException::forInvalidEstimatedCostValue($request->runId, (float)$value);
        }

        return new CostEstimate((float)$value, 'request_metadata');
    }
}
