<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Resilience;

use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\RuntimeBudgetExceededException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use RuntimeException;
use CreativeCrafts\LaravelAiAgentKit\Resilience\enums\CostBudgetMode;

final readonly class RuntimeBudgetEnforcer
{
    public function __construct(
        private ConfigRepository $config,
        private string $configKey = 'ai-agent-kit',
    ) {
    }

    public function assertPreflight(string $runId, ?CostEstimate $costEstimate): void
    {
        $maxCostUsd = $this->nullableNumericConfigValue($this->configKey . '.budgets.max_cost_usd');

        if ($maxCostUsd === null) {
            return;
        }

        if (!$costEstimate instanceof CostEstimate) {
            if ($this->costBudgetMode() === CostBudgetMode::Strict) {
                throw RuntimeBudgetExceededException::forMissingEstimatedCost($runId, $maxCostUsd);
            }

            return;
        }

        if ($costEstimate->amountUsd > $maxCostUsd) {
            throw RuntimeBudgetExceededException::forMaxCostUsd($runId, $maxCostUsd, $costEstimate->amountUsd);
        }
    }

    public function assertPostflight(
        string $runId,
        int $totalTokens,
        int $toolCallCount,
    ): void {
        $maxTokens = $this->nullableNumericConfigValue($this->configKey . '.budgets.max_tokens');

        if ($maxTokens !== null && $totalTokens > $maxTokens) {
            throw RuntimeBudgetExceededException::forMaxTokens($runId, $maxTokens, $totalTokens);
        }

        $maxToolCalls = $this->intConfigValue($this->configKey . '.budgets.max_tool_calls', 50);

        if ($toolCallCount > $maxToolCalls) {
            throw RuntimeBudgetExceededException::forMaxToolCalls($runId, $maxToolCalls, $toolCallCount);
        }

    }

    /**
     * Backward-compatible combined check for direct consumers of the enforcer.
     */
    public function assertResponseWithinBudgets(
        string $runId,
        int $totalTokens,
        int $toolCallCount,
        ?float $estimatedCostUsd,
    ): void {
        $estimate = $estimatedCostUsd === null
            ? null
            : new CostEstimate($estimatedCostUsd, 'legacy_argument');

        $this->assertPreflight($runId, $estimate);
        $this->assertPostflight($runId, $totalTokens, $toolCallCount);
    }

    public function isCostBudgetConfigured(): bool
    {
        return $this->nullableNumericConfigValue($this->configKey . '.budgets.max_cost_usd') !== null;
    }

    public function costBudgetMode(): CostBudgetMode
    {
        $value = $this->config->get(
            $this->configKey . '.budgets.cost_estimation_mode',
            CostBudgetMode::Strict->value,
        );

        if (!is_string($value) || CostBudgetMode::tryFrom($value) === null) {
            throw new RuntimeException(sprintf(
                'Configuration key [%s.budgets.cost_estimation_mode] must be strict or advisory.',
                $this->configKey,
            ));
        }

        return CostBudgetMode::from($value);
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
