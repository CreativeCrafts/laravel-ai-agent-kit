<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Resilience;

use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Exceptions\PipelineBudgetExceededException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use RuntimeException;

final readonly class PipelineBudgetEnforcer
{
    public function __construct(
        private ConfigRepository $config,
        private string $configKey = 'ai-agent-kit',
    ) {
    }

    public function assertCanExecuteStep(int $attemptedStepNumber, float $startedAt): void
    {
        $maxSteps = $this->intConfigValue($this->configKey . '.budgets.max_steps', 20);

        if ($attemptedStepNumber > $maxSteps) {
            throw PipelineBudgetExceededException::forMaxSteps($maxSteps, $attemptedStepNumber);
        }

        $this->assertWithinTimeout($startedAt);
    }

    public function assertWithinTimeout(float $startedAt): void
    {
        $maxTotalTimeoutSeconds = $this->intConfigValue($this->configKey . '.budgets.max_total_timeout_seconds', 120);
        $elapsedSeconds = max(0.0, microtime(true) - $startedAt);

        if ($elapsedSeconds > $maxTotalTimeoutSeconds) {
            throw PipelineBudgetExceededException::forTotalTimeout($maxTotalTimeoutSeconds, $elapsedSeconds);
        }
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
