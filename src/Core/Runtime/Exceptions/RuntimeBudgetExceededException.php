<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions;

use CreativeCrafts\LaravelAiAgentKit\Observability\Support\FailureCategory;
use RuntimeException;

final class RuntimeBudgetExceededException extends RuntimeException
{
    public static function forMaxTokens(string $runId, int|float $maxTokens, int $actualTotalTokens): self
    {
        return new self(
            message: sprintf(
                'AI runtime execution exceeded token budget for run [%s]: total_tokens [%d] exceeds max_tokens [%s].',
                $runId,
                $actualTotalTokens,
                (string)$maxTokens,
            ),
        );
    }

    public static function forMaxToolCalls(string $runId, int $maxToolCalls, int $actualToolCallCount): self
    {
        return new self(
            message: sprintf(
                'AI runtime execution exceeded tool call budget for run [%s]: tool_call_count [%d] exceeds max_tool_calls [%d].',
                $runId,
                $actualToolCallCount,
                $maxToolCalls,
            ),
        );
    }

    public static function forMaxCostUsd(string $runId, int|float $maxCostUsd, float $actualCostUsd): self
    {
        return new self(
            message: sprintf(
                'AI runtime execution exceeded cost budget for run [%s]: estimated_cost_usd [%.6f] exceeds max_cost_usd [%s].',
                $runId,
                $actualCostUsd,
                (string)$maxCostUsd,
            ),
        );
    }

    public static function forMissingEstimatedCost(string $runId, int|float $maxCostUsd): self
    {
        return new self(
            message: sprintf(
                'AI runtime execution requires [metadata.cost_usd] or [metadata.estimated_cost_usd] when max_cost_usd [%s] is configured for run [%s].',
                (string)$maxCostUsd,
                $runId,
            ),
        );
    }

    public static function forInvalidEstimatedCostType(string $runId, string $actualType): self
    {
        return new self(
            message: sprintf(
                'AI runtime execution received invalid estimated cost metadata for run [%s]: expected int|float but received [%s].',
                $runId,
                $actualType,
            ),
        );
    }

    public static function forInvalidEstimatedCostValue(string $runId, float $actualValue): self
    {
        return new self(
            message: sprintf(
                'AI runtime execution received invalid estimated cost metadata for run [%s]: value [%.6f] must be >= 0.',
                $runId,
                $actualValue,
            ),
        );
    }

    public function failureCategory(): string
    {
        return FailureCategory::BudgetExceeded->value;
    }
}
