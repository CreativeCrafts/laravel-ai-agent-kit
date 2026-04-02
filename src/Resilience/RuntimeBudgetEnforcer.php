<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Resilience;

use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\RuntimeBudgetExceededException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use RuntimeException;

final readonly class RuntimeBudgetEnforcer
{
    public function __construct(
        private ConfigRepository $config,
        private string $configKey = 'ai-agent-kit',
    ) {
    }

    public function assertResponseWithinBudgets(
        string $runId,
        int $totalTokens,
        int $toolCallCount,
        ?float $estimatedCostUsd,
    ): void {
        $maxTokens = $this->nullableNumericConfigValue($this->configKey . '.budgets.max_tokens');

        if ($maxTokens !== null && $totalTokens > $maxTokens) {
            throw RuntimeBudgetExceededException::forMaxTokens($runId, $maxTokens, $totalTokens);
        }

        $maxToolCalls = $this->intConfigValue($this->configKey . '.budgets.max_tool_calls', 50);

        if ($toolCallCount > $maxToolCalls) {
            throw RuntimeBudgetExceededException::forMaxToolCalls($runId, $maxToolCalls, $toolCallCount);
        }

        $maxCostUsd = $this->nullableNumericConfigValue($this->configKey . '.budgets.max_cost_usd');

        if ($maxCostUsd === null) {
            return;
        }

        if ($estimatedCostUsd === null) {
            throw RuntimeBudgetExceededException::forMissingEstimatedCost($runId, $maxCostUsd);
        }

        if ($estimatedCostUsd > $maxCostUsd) {
            throw RuntimeBudgetExceededException::forMaxCostUsd($runId, $maxCostUsd, $estimatedCostUsd);
        }
    }

    private function nullableNumericConfigValue(string $key): int|float|null
    {
        $value = $this->config->get($key);

        if ($value === null) {
            return null;
        }

        if (!is_int($value) && !is_float($value)) {
            throw new RuntimeException(sprintf('Configuration key [%s] must be int|float|null.', $key));
        }

        if ($value < 0) {
            throw new RuntimeException(sprintf('Configuration key [%s] must be >= 0.', $key));
        }

        return $value;
    }

    private function intConfigValue(string $key, int $default): int
    {
        $value = $this->config->get($key, $default);

        if (!is_int($value)) {
            throw new RuntimeException(sprintf('Configuration key [%s] must be an integer.', $key));
        }

        return $value;
    }
}
