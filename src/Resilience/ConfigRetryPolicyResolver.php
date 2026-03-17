<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Resilience;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience\RetryPolicyResolver;
use CreativeCrafts\LaravelAiAgentKit\Resilience\Exceptions\InvalidRetryPolicyException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final readonly class ConfigRetryPolicyResolver implements RetryPolicyResolver
{
    public function __construct(
        private ConfigRepository $config,
        private string $configKey = 'ai-agent-kit',
    ) {
    }

    public function resolve(): RetryPolicy
    {
        $retry = $this->requireArray($this->config->get($this->configKey . '.resilience.retry', []), $this->configKey . '.resilience.retry');
        $backoff = $this->requireArray($retry['backoff'] ?? [], $this->configKey . '.resilience.retry.backoff');

        $strategyValue = $backoff['strategy'] ?? BackoffStrategy::Exponential->value;

        if (!is_string($strategyValue) || $strategyValue === '') {
            throw InvalidRetryPolicyException::invalidConfigType($this->configKey . '.resilience.retry.backoff.strategy', 'non-empty string');
        }

        $strategy = BackoffStrategy::tryFrom($strategyValue)
          ?? throw InvalidRetryPolicyException::unsupportedStrategy($strategyValue);

        $policy = new RetryPolicy(
            enabled: $this->boolValue($retry, 'enabled', true, $this->configKey . '.resilience.retry.enabled'),
            maxAttempts: $this->intValue($retry, 'max_attempts', 3, $this->configKey . '.resilience.retry.max_attempts'),
            backoff: new BackoffStrategyConfig(
                strategy: $strategy,
                baseDelayMs: $this->intValue($backoff, 'base_delay_ms', 250, $this->configKey . '.resilience.retry.backoff.base_delay_ms'),
                maxDelayMs: $this->intValue($backoff, 'max_delay_ms', 2000, $this->configKey . '.resilience.retry.backoff.max_delay_ms'),
                multiplier: $this->floatValue($backoff, 'multiplier', 2.0, $this->configKey . '.resilience.retry.backoff.multiplier'),
            ),
        );

        $budgets = $this->requireArray($this->config->get($this->configKey . '.budgets', []), $this->configKey . '.budgets');

        return $policy->boundedToMaxRetries(
            $this->intValue(
                $budgets,
                'max_retries_per_step',
                2,
                $this->configKey . '.budgets.max_retries_per_step',
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function requireArray(mixed $value, string $key): array
    {
        if (!is_array($value)) {
            throw InvalidRetryPolicyException::invalidConfigType($key, 'array');
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function boolValue(array $config, string $key, bool $default, string $path): bool
    {
        $value = $config[$key] ?? $default;

        if (!is_bool($value)) {
            throw InvalidRetryPolicyException::invalidConfigType($path, 'bool');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function intValue(array $config, string $key, int $default, string $path): int
    {
        $value = $config[$key] ?? $default;

        if (!is_int($value)) {
            throw InvalidRetryPolicyException::invalidConfigType($path, 'int');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function floatValue(array $config, string $key, float $default, string $path): float
    {
        $value = $config[$key] ?? $default;

        if (!is_int($value) && !is_float($value)) {
            throw InvalidRetryPolicyException::invalidConfigType($path, 'int|float');
        }

        return (float)$value;
    }
}
