<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Config;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\RuntimeMiddleware;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\AudioGenerationRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\EmbeddingsRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\ImageGenerationRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\RerankingRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\TranscriptionRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolAuthorizer;
use CreativeCrafts\LaravelAiAgentKit\Core\Config\Exceptions\InvalidConfigurationException;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\DelegationPolicyMode;
use CreativeCrafts\LaravelAiAgentKit\Resilience\enums\BackoffStrategy;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final readonly class ConfigValidator
{
    public function __construct(
        private ConfigRepository $config,
        private string $configKey = 'ai-agent-kit',
    ) {
    }

    /**
     * Validate the currently loaded config (fail-fast).
     *
     * @throws InvalidConfigurationException
     */
    public function validateCurrentConfig(): void
    {
        $config = $this->config->get($this->configKey);

        if ($config === null) {
            throw InvalidConfigurationException::missingKey($this->configKey);
        }

        if (!is_array($config)) {
            throw InvalidConfigurationException::invalidType($this->configKey, 'array');
        }

        /** @var array<string, mixed> $config */
        $this->validate($config);
    }

    /**
     * @param array<string, mixed> $config
     * @throws InvalidConfigurationException
     */
    public function validate(array $config): void
    {
        $this->validateValidationSection($config);

        $providers = $this->requireArray($config, 'providers');

        if ($providers === []) {
            throw InvalidConfigurationException::invalidValue('providers', 'At least one provider must be configured.');
        }

        foreach ($providers as $name => $provider) {
            if (!is_string($name) || $name === '') {
                throw InvalidConfigurationException::invalidValue('providers', 'Provider names must be non-empty strings.');
            }

            if (!is_array($provider)) {
                throw InvalidConfigurationException::invalidType("providers.{$name}", 'array');
            }

            $driver = $provider['driver'] ?? null;

            if (!is_string($driver) || $driver === '') {
                throw InvalidConfigurationException::invalidValue("providers.{$name}.driver", 'Must be a non-empty string.');
            }

            if (array_key_exists('enabled', $provider) && !is_bool($provider['enabled'])) {
                throw InvalidConfigurationException::invalidType("providers.{$name}.enabled", 'bool');
            }

            if (array_key_exists('options', $provider) && !is_array($provider['options'])) {
                throw InvalidConfigurationException::invalidType("providers.{$name}.options", 'array');
            }

            if (array_key_exists('capabilities', $provider)) {
                if (!is_array($provider['capabilities'])) {
                    throw InvalidConfigurationException::invalidType("providers.{$name}.capabilities", 'array');
                }

                $seenCapabilities = [];

                foreach ($provider['capabilities'] as $index => $capability) {
                    if (!is_string($capability) || $capability === '') {
                        throw InvalidConfigurationException::invalidValue(
                            "providers.{$name}.capabilities.{$index}",
                            'Must be a non-empty string capability name.',
                        );
                    }

                    if (isset($seenCapabilities[$capability])) {
                        throw InvalidConfigurationException::invalidValue(
                            "providers.{$name}.capabilities",
                            "Duplicate capability '{$capability}' is not allowed.",
                        );
                    }

                    $seenCapabilities[$capability] = true;
                }
            }
        }

        $defaultProvider = $config['default_provider'] ?? null;

        if (!is_string($defaultProvider) || $defaultProvider === '') {
            throw InvalidConfigurationException::invalidValue('default_provider', 'Must be a non-empty string.');
        }

        if (!array_key_exists($defaultProvider, $providers)) {
            throw InvalidConfigurationException::invalidValue('default_provider', 'Must reference an entry in providers.');
        }

        if (!$this->isProviderEnabled($providers, $defaultProvider)) {
            throw InvalidConfigurationException::invalidValue('default_provider', 'Default provider must be enabled.');
        }

        $failover = $this->requireArray($config, 'failover_order');

        if ($failover === []) {
            throw InvalidConfigurationException::invalidValue('failover_order', 'Must contain at least the default provider.');
        }

        $seen = [];

        foreach ($failover as $idx => $providerName) {
            if (!is_string($providerName) || $providerName === '') {
                throw InvalidConfigurationException::invalidValue(
                    "failover_order.{$idx}",
                    'Must be a non-empty string provider name.',
                );
            }

            if (isset($seen[$providerName])) {
                throw InvalidConfigurationException::invalidValue(
                    'failover_order',
                    "Duplicate provider '{$providerName}' is not allowed.",
                );
            }

            $seen[$providerName] = true;

            if (!array_key_exists($providerName, $providers)) {
                throw InvalidConfigurationException::invalidValue(
                    'failover_order',
                    "Provider '{$providerName}' is not defined in providers.",
                );
            }

            if (!$this->isProviderEnabled($providers, $providerName)) {
                throw InvalidConfigurationException::invalidValue(
                    'failover_order',
                    "Provider '{$providerName}' is disabled but included in failover_order.",
                );
            }
        }

        if (!isset($seen[$defaultProvider])) {
            throw InvalidConfigurationException::invalidValue('failover_order', 'Must include the default_provider.');
        }

        $this->validateBudgets($config);
        $this->validateOrchestration($config);
        $this->validateResilience($config);
        $this->validatePrompts($config);
        $this->validateMemory($config);

        $this->validateMemoryAttachmentsReplay($config);
        $this->validateVector($config);
        $this->validateTools($config);
        $this->validateSummarization($config);
        $this->validateRuntime($config);
        $this->validateModalities($config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validateValidationSection(array $config): void
    {
        if (!array_key_exists('validation', $config)) {
            return;
        }

        if (!is_array($config['validation'])) {
            throw InvalidConfigurationException::invalidType('validation', 'array');
        }

        if (array_key_exists('enabled', $config['validation']) && !is_bool($config['validation']['enabled'])) {
            throw InvalidConfigurationException::invalidType('validation.enabled', 'bool');
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return array<int|string, mixed>
     */
    private function requireArray(array $config, string $key): array
    {
        if (!array_key_exists($key, $config)) {
            throw InvalidConfigurationException::missingKey($key);
        }

        if (!is_array($config[$key])) {
            throw InvalidConfigurationException::invalidType($key, 'array');
        }

        return $config[$key];
    }

    /**
     * @param array<int|string, mixed> $providers
     */
    private function isProviderEnabled(array $providers, string $providerName): bool
    {
        $provider = $providers[$providerName] ?? null;

        if (!is_array($provider)) {
            return false;
        }

        $enabled = $provider['enabled'] ?? true;

        if (!is_bool($enabled)) {
            return false;
        }

        return $enabled;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validateBudgets(array $config): void
    {
        if (!array_key_exists('budgets', $config)) {
            return;
        }

        if (!is_array($config['budgets'])) {
            throw InvalidConfigurationException::invalidType('budgets', 'array');
        }

        $intKeys = [
          'max_steps',
          'max_orchestration_depth',
          'max_tool_calls',
          'max_retries_per_step',
          'max_total_timeout_seconds',
        ];

        foreach ($intKeys as $key) {
            if (!array_key_exists($key, $config['budgets'])) {
                continue;
            }

            $value = $config['budgets'][$key];

            if (!is_int($value) || $value < 1) {
                throw InvalidConfigurationException::invalidValue("budgets.{$key}", 'Must be an integer >= 1.');
            }
        }

        foreach (['max_tokens', 'max_cost_usd'] as $key) {
            if (!array_key_exists($key, $config['budgets'])) {
                continue;
            }

            $value = $config['budgets'][$key];

            if ($value === null) {
                continue;
            }

            if (!is_int($value) && !is_float($value)) {
                throw InvalidConfigurationException::invalidType("budgets.{$key}", 'int|float|null');
            }

            if ($value < 0) {
                throw InvalidConfigurationException::invalidValue("budgets.{$key}", 'Must be >= 0.');
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validateOrchestration(array $config): void
    {
        if (!array_key_exists('orchestration', $config)) {
            return;
        }

        if (!is_array($config['orchestration'])) {
            throw InvalidConfigurationException::invalidType('orchestration', 'array');
        }

        $orchestration = $config['orchestration'];

        if (!array_key_exists('delegation_policy', $orchestration)) {
            return;
        }

        if (!is_array($orchestration['delegation_policy'])) {
            throw InvalidConfigurationException::invalidType('orchestration.delegation_policy', 'array');
        }

        $delegationPolicy = $orchestration['delegation_policy'];

        if (array_key_exists('mode', $delegationPolicy)) {
            $mode = $delegationPolicy['mode'];

            if ($mode instanceof DelegationPolicyMode) {
                $mode = $mode->value;
            }

            if (!is_string($mode) || $mode === '') {
                throw InvalidConfigurationException::invalidValue(
                    'orchestration.delegation_policy.mode',
                    'Must be a non-empty string or a delegation policy mode enum.',
                );
            }

            if (!in_array($mode, array_map(static fn (DelegationPolicyMode $candidate): string => $candidate->value, DelegationPolicyMode::cases()), true)) {
                throw InvalidConfigurationException::invalidValue(
                    'orchestration.delegation_policy.mode',
                    sprintf(
                        'Must be one of [%s].',
                        implode(', ', array_map(static fn (DelegationPolicyMode $candidate): string => $candidate->value, DelegationPolicyMode::cases())),
                    ),
                );
            }
        }

        if (array_key_exists('allowlist', $delegationPolicy) && !is_array($delegationPolicy['allowlist'])) {
            throw InvalidConfigurationException::invalidType('orchestration.delegation_policy.allowlist', 'array');
        }

        if (array_key_exists('rewrites', $delegationPolicy) && !is_array($delegationPolicy['rewrites'])) {
            throw InvalidConfigurationException::invalidType('orchestration.delegation_policy.rewrites', 'array');
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validateResilience(array $config): void
    {
        if (!array_key_exists('resilience', $config)) {
            return;
        }

        if (!is_array($config['resilience'])) {
            throw InvalidConfigurationException::invalidType('resilience', 'array');
        }

        $resilience = $config['resilience'];

        if (array_key_exists('retry', $resilience)) {
            if (!is_array($resilience['retry'])) {
                throw InvalidConfigurationException::invalidType('resilience.retry', 'array');
            }

            $retry = $resilience['retry'];

            if (array_key_exists('enabled', $retry) && !is_bool($retry['enabled'])) {
                throw InvalidConfigurationException::invalidType('resilience.retry.enabled', 'bool');
            }

            if (array_key_exists('max_attempts', $retry)) {
                $maxAttempts = $retry['max_attempts'];

                if (!is_int($maxAttempts) || $maxAttempts < 1) {
                    throw InvalidConfigurationException::invalidValue('resilience.retry.max_attempts', 'Must be an integer >= 1.');
                }
            }

            if (array_key_exists('backoff', $retry)) {
                if (!is_array($retry['backoff'])) {
                    throw InvalidConfigurationException::invalidType('resilience.retry.backoff', 'array');
                }

                $backoff = $retry['backoff'];

                if (array_key_exists('strategy', $backoff)) {
                    $strategy = $backoff['strategy'];

                    if (!is_string($strategy) || BackoffStrategy::tryFrom($strategy) === null) {
                        throw InvalidConfigurationException::invalidValue(
                            'resilience.retry.backoff.strategy',
                            'Must be one of: constant, linear, exponential.',
                        );
                    }
                }

                if (array_key_exists('base_delay_ms', $backoff)) {
                    $baseDelay = $backoff['base_delay_ms'];

                    if (!is_int($baseDelay) || $baseDelay < 0) {
                        throw InvalidConfigurationException::invalidValue('resilience.retry.backoff.base_delay_ms', 'Must be an integer >= 0.');
                    }
                }

                if (array_key_exists('max_delay_ms', $backoff)) {
                    $maxDelay = $backoff['max_delay_ms'];

                    if (!is_int($maxDelay) || $maxDelay < 0) {
                        throw InvalidConfigurationException::invalidValue('resilience.retry.backoff.max_delay_ms', 'Must be an integer >= 0.');
                    }

                    $baseDelay = $backoff['base_delay_ms'] ?? 0;

                    if (is_int($baseDelay) && $maxDelay < $baseDelay) {
                        throw InvalidConfigurationException::invalidValue(
                            'resilience.retry.backoff.max_delay_ms',
                            'Must be greater than or equal to resilience.retry.backoff.base_delay_ms.',
                        );
                    }
                }

                if (array_key_exists('multiplier', $backoff)) {
                    $multiplier = $backoff['multiplier'];

                    if ((!is_int($multiplier) && !is_float($multiplier)) || $multiplier < 1) {
                        throw InvalidConfigurationException::invalidValue('resilience.retry.backoff.multiplier', 'Must be a numeric value >= 1.');
                    }
                }
            }
        }

        if (!array_key_exists('circuit_breaker', $resilience)) {
            return;
        }

        if (!is_array($resilience['circuit_breaker'])) {
            throw InvalidConfigurationException::invalidType('resilience.circuit_breaker', 'array');
        }

        $circuitBreaker = $resilience['circuit_breaker'];

        if (array_key_exists('enabled', $circuitBreaker) && !is_bool($circuitBreaker['enabled'])) {
            throw InvalidConfigurationException::invalidType('resilience.circuit_breaker.enabled', 'bool');
        }

        if (array_key_exists('apply_to_failover', $circuitBreaker) && !is_bool($circuitBreaker['apply_to_failover'])) {
            throw InvalidConfigurationException::invalidType('resilience.circuit_breaker.apply_to_failover', 'bool');
        }

        foreach (['failure_threshold', 'reset_timeout_seconds', 'half_open_success_threshold'] as $key) {
            if (!array_key_exists($key, $circuitBreaker)) {
                continue;
            }

            $value = $circuitBreaker[$key];

            if (!is_int($value) || $value < 1) {
                throw InvalidConfigurationException::invalidValue("resilience.circuit_breaker.{$key}", 'Must be an integer >= 1.');
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validatePrompts(array $config): void
    {
        if (!array_key_exists('prompts', $config)) {
            return;
        }

        if (!is_array($config['prompts'])) {
            throw InvalidConfigurationException::invalidType('prompts', 'array');
        }

        $prompts = $config['prompts'];

        if (array_key_exists('default_driver', $prompts)) {
            $defaultDriver = $prompts['default_driver'];

            if (!is_string($defaultDriver) || $defaultDriver === '') {
                throw InvalidConfigurationException::invalidValue('prompts.default_driver', 'Must be a non-empty string.');
            }

            if (!in_array($defaultDriver, ['in_memory', 'file'], true)) {
                throw InvalidConfigurationException::invalidValue(
                    'prompts.default_driver',
                    'Must be one of: in_memory, file.',
                );
            }
        }

        if (!array_key_exists('file', $prompts)) {
            return;
        }

        if (!is_array($prompts['file'])) {
            throw InvalidConfigurationException::invalidType('prompts.file', 'array');
        }

        $file = $prompts['file'];

        if (array_key_exists('root_path', $file) && $file['root_path'] !== null && (!is_string($file['root_path']) || $file['root_path'] === '')) {
            throw InvalidConfigurationException::invalidValue('prompts.file.root_path', 'Must be null or a non-empty string.');
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validateMemory(array $config): void
    {
        if (!array_key_exists('memory', $config)) {
            return;
        }

        if (!is_array($config['memory'])) {
            throw InvalidConfigurationException::invalidType('memory', 'array');
        }

        if (array_key_exists('default_driver', $config['memory'])) {
            $defaultDriver = $config['memory']['default_driver'];

            if (!is_string($defaultDriver) || $defaultDriver === '') {
                throw InvalidConfigurationException::invalidValue('memory.default_driver', 'Must be a non-empty string.');
            }

            if (!in_array($defaultDriver, ['database', 'in_memory', 'redis'], true)) {
                throw InvalidConfigurationException::invalidValue('memory.default_driver', 'Must be one of: database, in_memory, redis.');
            }
        }

        if (array_key_exists('in_memory', $config['memory'])) {
            if (!is_array($config['memory']['in_memory'])) {
                throw InvalidConfigurationException::invalidType('memory.in_memory', 'array');
            }

            $inMemory = $config['memory']['in_memory'];

            if (array_key_exists('retention_days', $inMemory)) {
                $retentionDays = $inMemory['retention_days'];

                if ($retentionDays !== null && (!is_int($retentionDays) || $retentionDays < 1)) {
                    throw InvalidConfigurationException::invalidValue('memory.in_memory.retention_days', 'Must be null or an integer >= 1.');
                }
            }
        }

        if (array_key_exists('database', $config['memory'])) {
            if (!is_array($config['memory']['database'])) {
                throw InvalidConfigurationException::invalidType('memory.database', 'array');
            }

            $database = $config['memory']['database'];

            foreach (['conversations_table', 'messages_table', 'driver_name'] as $key) {
                if (!array_key_exists($key, $database)) {
                    continue;
                }

                $value = $database[$key];

                if (!is_string($value) || $value === '') {
                    throw InvalidConfigurationException::invalidValue("memory.database.{$key}", 'Must be a non-empty string.');
                }
            }

            if (array_key_exists('connection', $database)) {
                $connection = $database['connection'];

                if ($connection !== null && (!is_string($connection) || $connection === '')) {
                    throw InvalidConfigurationException::invalidType('memory.database.connection', 'string|null');
                }
            }

            if (array_key_exists('retention_days', $database)) {
                $retentionDays = $database['retention_days'];

                if ($retentionDays !== null && (!is_int($retentionDays) || $retentionDays < 1)) {
                    throw InvalidConfigurationException::invalidValue('memory.database.retention_days', 'Must be null or an integer >= 1.');
                }
            }

            if (array_key_exists('encrypt_payloads', $database) && !is_bool($database['encrypt_payloads'])) {
                throw InvalidConfigurationException::invalidType('memory.database.encrypt_payloads', 'bool');
            }
        }

        if (array_key_exists('laravel_ai_legacy', $config['memory'])) {
            if (!is_array($config['memory']['laravel_ai_legacy'])) {
                throw InvalidConfigurationException::invalidType('memory.laravel_ai_legacy', 'array');
            }

            $legacy = $config['memory']['laravel_ai_legacy'];

            if (array_key_exists('enabled', $legacy) && !is_bool($legacy['enabled'])) {
                throw InvalidConfigurationException::invalidType('memory.laravel_ai_legacy.enabled', 'bool');
            }

            if (array_key_exists('connection', $legacy)) {
                $connection = $legacy['connection'];

                if ($connection !== null && (!is_string($connection) || $connection === '')) {
                    throw InvalidConfigurationException::invalidType('memory.laravel_ai_legacy.connection', 'string|null');
                }
            }

            foreach (['conversations_table', 'messages_table'] as $key) {
                if (!array_key_exists($key, $legacy)) {
                    continue;
                }

                $value = $legacy[$key];

                if (!is_string($value) || $value === '') {
                    throw InvalidConfigurationException::invalidValue(
                        "memory.laravel_ai_legacy.{$key}",
                        'Must be a non-empty string.',
                    );
                }
            }
        }

        if (array_key_exists('redis', $config['memory'])) {
            if (!is_array($config['memory']['redis'])) {
                throw InvalidConfigurationException::invalidType('memory.redis', 'array');
            }

            $redis = $config['memory']['redis'];

            foreach (['prefix', 'driver_name'] as $key) {
                if (!array_key_exists($key, $redis)) {
                    continue;
                }

                $value = $redis[$key];

                if (!is_string($value) || $value === '') {
                    throw InvalidConfigurationException::invalidValue("memory.redis.{$key}", 'Must be a non-empty string.');
                }
            }

            if (array_key_exists('connection', $redis)) {
                $connection = $redis['connection'];

                if ($connection !== null && (!is_string($connection) || $connection === '')) {
                    throw InvalidConfigurationException::invalidType('memory.redis.connection', 'string|null');
                }
            }

            if (array_key_exists('retention_days', $redis)) {
                $retentionDays = $redis['retention_days'];

                if ($retentionDays !== null && (!is_int($retentionDays) || $retentionDays < 1)) {
                    throw InvalidConfigurationException::invalidValue('memory.redis.retention_days', 'Must be null or an integer >= 1.');
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validateMemoryAttachmentsReplay(array $config): void
    {
        if (!isset($config['memory']) || !is_array($config['memory'])) {
            return;
        }

        $memory = $config['memory'];

        if (!array_key_exists('attachments_replay', $memory)) {
            return;
        }

        if (!is_array($memory['attachments_replay'])) {
            throw InvalidConfigurationException::invalidType('memory.attachments_replay', 'array');
        }

        $replay = $memory['attachments_replay'];

        if (array_key_exists('enabled', $replay) && !is_bool($replay['enabled'])) {
            throw InvalidConfigurationException::invalidType('memory.attachments_replay.enabled', 'bool');
        }

        foreach (['max_per_turn', 'max_age_seconds'] as $key) {
            if (!array_key_exists($key, $replay)) {
                continue;
            }

            $value = $replay[$key];

            if ($value !== null && (!is_int($value) || $value < 1)) {
                throw InvalidConfigurationException::invalidValue(
                    "memory.attachments_replay.{$key}",
                    'Must be null or an integer >= 1.',
                );
            }
        }

        if (array_key_exists('allow_provider_references', $replay) && !is_bool($replay['allow_provider_references'])) {
            throw InvalidConfigurationException::invalidType('memory.attachments_replay.allow_provider_references', 'bool');
        }

        if (array_key_exists('deny_types', $replay)) {
            if (!is_array($replay['deny_types'])) {
                throw InvalidConfigurationException::invalidType('memory.attachments_replay.deny_types', 'array');
            }

            foreach ($replay['deny_types'] as $i => $type) {
                if (!is_string($type) || $type === '') {
                    throw InvalidConfigurationException::invalidValue(
                        "memory.attachments_replay.deny_types.{$i}",
                        'Must be a non-empty string.',
                    );
                }
            }
        }

        if (array_key_exists('deny_url_substrings', $replay)) {
            if (!is_array($replay['deny_url_substrings'])) {
                throw InvalidConfigurationException::invalidType('memory.attachments_replay.deny_url_substrings', 'array');
            }

            foreach ($replay['deny_url_substrings'] as $i => $fragment) {
                if (!is_string($fragment) || $fragment === '') {
                    throw InvalidConfigurationException::invalidValue(
                        "memory.attachments_replay.deny_url_substrings.{$i}",
                        'Must be a non-empty string.',
                    );
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validateVector(array $config): void
    {
        if (!array_key_exists('vector', $config)) {
            return;
        }

        if (!is_array($config['vector'])) {
            throw InvalidConfigurationException::invalidType('vector', 'array');
        }

        $vector = $config['vector'];

        if (array_key_exists('default_driver', $vector)) {
            $defaultDriver = $vector['default_driver'];

            if (!is_string($defaultDriver) || $defaultDriver === '') {
                throw InvalidConfigurationException::invalidValue('vector.default_driver', 'Must be a non-empty string.');
            }

            if ($defaultDriver !== 'in_memory') {
                throw InvalidConfigurationException::invalidValue('vector.default_driver', 'Must be one of: in_memory.');
            }
        }

        if (array_key_exists('in_memory', $vector)) {
            if (!is_array($vector['in_memory'])) {
                throw InvalidConfigurationException::invalidType('vector.in_memory', 'array');
            }

            $inMemory = $vector['in_memory'];

            if (array_key_exists('enabled', $inMemory) && !is_bool($inMemory['enabled'])) {
                throw InvalidConfigurationException::invalidType('vector.in_memory.enabled', 'bool');
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validateTools(array $config): void
    {
        if (!array_key_exists('tools', $config)) {
            return;
        }

        if (!is_array($config['tools'])) {
            throw InvalidConfigurationException::invalidType('tools', 'array');
        }

        $tools = $config['tools'];

        if (array_key_exists('authorizer', $tools)) {
            $authorizer = $tools['authorizer'];

            if (!is_string($authorizer) || $authorizer === '') {
                throw InvalidConfigurationException::invalidValue('tools.authorizer', 'Must be a non-empty class-string.');
            }

            if (!class_exists($authorizer)) {
                throw InvalidConfigurationException::invalidValue('tools.authorizer', 'Configured tool authorizer class does not exist.');
            }

            if (!is_a($authorizer, ToolAuthorizer::class, true)) {
                throw InvalidConfigurationException::invalidValue(
                    'tools.authorizer',
                    sprintf(
                        'Configured tool authorizer [%s] must implement [%s].',
                        $authorizer,
                        ToolAuthorizer::class,
                    ),
                );
            }
        }

        if (!array_key_exists('provider_tools', $tools)) {
            return;
        }

        if (!is_array($tools['provider_tools'])) {
            throw InvalidConfigurationException::invalidType('tools.provider_tools', 'array');
        }

        foreach ($tools['provider_tools'] as $name => $tool) {
            if (!is_string($name) || $name === '') {
                throw InvalidConfigurationException::invalidValue('tools.provider_tools', 'Tool aliases must be non-empty strings.');
            }

            if (!is_array($tool)) {
                throw InvalidConfigurationException::invalidType("tools.provider_tools.{$name}", 'array');
            }

            /** @var array<string, mixed> $tool */
            $tool = array_filter(
                $tool,
                static fn (int|string $key): bool => is_string($key),
                ARRAY_FILTER_USE_KEY,
            );

            $type = $tool['type'] ?? null;

            if (!is_string($type) || $type === '') {
                throw InvalidConfigurationException::invalidValue("tools.provider_tools.{$name}.type", 'Must be a non-empty string.');
            }

            if (!in_array($type, ['web_search', 'web_fetch', 'file_search'], true)) {
                throw InvalidConfigurationException::invalidValue(
                    "tools.provider_tools.{$name}.type",
                    'Must be one of: web_search, web_fetch, file_search.',
                );
            }

            if (array_key_exists('enabled', $tool) && !is_bool($tool['enabled'])) {
                throw InvalidConfigurationException::invalidType("tools.provider_tools.{$name}.enabled", 'bool');
            }

            if ($type === 'web_search') {
                $this->validateWebSearchTool($name, $tool);

                continue;
            }

            if ($type === 'web_fetch') {
                $this->validateWebFetchTool($name, $tool);

                continue;
            }

            $this->validateFileSearchTool($name, $tool);
        }
    }

    /**
     * @param array<string, mixed> $tool
     */
    private function validateWebSearchTool(string $name, array $tool): void
    {
        $this->validateWebProviderTool($name, $tool);

        if (!array_key_exists('location', $tool)) {
            return;
        }

        if (!is_array($tool['location'])) {
            throw InvalidConfigurationException::invalidType(
                "tools.provider_tools.{$name}.location",
                'array',
            );
        }

        foreach (['city', 'region', 'country'] as $key) {
            if (!array_key_exists($key, $tool['location'])) {
                continue;
            }

            $value = $tool['location'][$key];

            if (!is_string($value) || $value === '') {
                throw InvalidConfigurationException::invalidValue(
                    "tools.provider_tools.{$name}.location.{$key}",
                    'Must be a non-empty string.',
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $tool
     */
    private function validateWebProviderTool(string $name, array $tool): void
    {
        if (array_key_exists('max_searches', $tool)) {
            $maxSearches = $tool['max_searches'];

            if (!is_int($maxSearches) || $maxSearches < 1) {
                throw InvalidConfigurationException::invalidValue(
                    "tools.provider_tools.{$name}.max_searches",
                    'Must be an integer >= 1.',
                );
            }
        }

        if (!array_key_exists('allowed_domains', $tool)) {
            return;
        }

        $domains = $tool['allowed_domains'];

        if (!is_array($domains)) {
            throw InvalidConfigurationException::invalidType(
                "tools.provider_tools.{$name}.allowed_domains",
                'array',
            );
        }

        foreach ($domains as $idx => $domain) {
            if (!is_string($domain) || $domain === '') {
                throw InvalidConfigurationException::invalidValue(
                    "tools.provider_tools.{$name}.allowed_domains.{$idx}",
                    'Must be a non-empty string domain.',
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $tool
     */
    private function validateWebFetchTool(string $name, array $tool): void
    {
        $this->validateWebProviderTool($name, $tool);
    }

    /**
     * @param array<string, mixed> $tool
     */
    private function validateFileSearchTool(string $name, array $tool): void
    {
        if (!array_key_exists('stores', $tool)) {
            throw InvalidConfigurationException::missingKey("tools.provider_tools.{$name}.stores");
        }

        $stores = $tool['stores'];

        if (!is_array($stores)) {
            throw InvalidConfigurationException::invalidType(
                "tools.provider_tools.{$name}.stores",
                'array',
            );
        }

        if ($stores === []) {
            throw InvalidConfigurationException::invalidValue(
                "tools.provider_tools.{$name}.stores",
                'Must contain at least one store identifier.',
            );
        }

        foreach ($stores as $idx => $store) {
            if (!is_string($store) || $store === '') {
                throw InvalidConfigurationException::invalidValue(
                    "tools.provider_tools.{$name}.stores.{$idx}",
                    'Must be a non-empty string store identifier.',
                );
            }
        }

        if (array_key_exists('filters', $tool) && !is_array($tool['filters'])) {
            throw InvalidConfigurationException::invalidType(
                "tools.provider_tools.{$name}.filters",
                'array',
            );
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validateSummarization(array $config): void
    {
        if (!array_key_exists('summarization', $config)) {
            return;
        }

        if (!is_array($config['summarization'])) {
            throw InvalidConfigurationException::invalidType('summarization', 'array');
        }

        $summarization = $config['summarization'];

        if (array_key_exists('enabled', $summarization) && !is_bool($summarization['enabled'])) {
            throw InvalidConfigurationException::invalidType('summarization.enabled', 'bool');
        }

        if (array_key_exists('trigger_message_count', $summarization)) {
            $triggerMessageCount = $summarization['trigger_message_count'];

            if (!is_int($triggerMessageCount) || $triggerMessageCount < 1) {
                throw InvalidConfigurationException::invalidValue('summarization.trigger_message_count', 'Must be an integer >= 1.');
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validateRuntime(array $config): void
    {
        if (!array_key_exists('runtime', $config)) {
            return;
        }

        if (!is_array($config['runtime'])) {
            throw InvalidConfigurationException::invalidType('runtime', 'array');
        }

        $runtime = $config['runtime'];

        if (array_key_exists('middleware', $runtime)) {
            if (!is_array($runtime['middleware'])) {
                throw InvalidConfigurationException::invalidType('runtime.middleware', 'array');
            }

            foreach ($runtime['middleware'] as $index => $class) {
                if (!is_string($class) || $class === '') {
                    throw InvalidConfigurationException::invalidValue(
                        "runtime.middleware.{$index}",
                        'Must be a non-empty class-string implementing RuntimeMiddleware.',
                    );
                }

                if (!class_exists($class)) {
                    throw InvalidConfigurationException::invalidValue(
                        "runtime.middleware.{$index}",
                        sprintf('Class [%s] does not exist.', $class),
                    );
                }

                if (!is_subclass_of($class, RuntimeMiddleware::class)) {
                    throw InvalidConfigurationException::invalidValue(
                        "runtime.middleware.{$index}",
                        sprintf('Class [%s] must implement %s.', $class, RuntimeMiddleware::class),
                    );
                }
            }
        }

        if (!array_key_exists('streaming', $runtime)) {
            return;
        }

        if (!is_array($runtime['streaming'])) {
            throw InvalidConfigurationException::invalidType('runtime.streaming', 'array');
        }

        $streaming = $runtime['streaming'];

        if (array_key_exists('broadcast_channel', $streaming)
            && $streaming['broadcast_channel'] !== null
            && (!is_string($streaming['broadcast_channel']) || $streaming['broadcast_channel'] === '')) {
            throw InvalidConfigurationException::invalidValue(
                'runtime.streaming.broadcast_channel',
                'Must be null or a non-empty string channel name.',
            );
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validateModalities(array $config): void
    {
        if (!array_key_exists('modalities', $config)) {
            return;
        }

        if (!is_array($config['modalities'])) {
            throw InvalidConfigurationException::invalidType('modalities', 'array');
        }

        $modalities = $config['modalities'];

        $sections = [
            'transcription' => TranscriptionRuntime::class,
            'embeddings' => EmbeddingsRuntime::class,
            'image_generation' => ImageGenerationRuntime::class,
            'reranking' => RerankingRuntime::class,
            'audio_generation' => AudioGenerationRuntime::class,
        ];

        foreach ($sections as $section => $contract) {
            if (!array_key_exists($section, $modalities)) {
                continue;
            }

            if (!is_array($modalities[$section])) {
                throw InvalidConfigurationException::invalidType("modalities.{$section}", 'array');
            }

            $block = $modalities[$section];

            if (!array_key_exists('default_driver', $block)) {
                continue;
            }

            $driver = $block['default_driver'];

            if (!is_string($driver) || $driver === '') {
                throw InvalidConfigurationException::invalidValue(
                    "modalities.{$section}.default_driver",
                    'Must be a non-empty string.',
                );
            }

            if ($driver === 'sdk') {
                continue;
            }

            if (!class_exists($driver)) {
                throw InvalidConfigurationException::invalidValue(
                    "modalities.{$section}.default_driver",
                    sprintf('Class [%s] does not exist.', $driver),
                );
            }

            if (!is_subclass_of($driver, $contract)) {
                throw InvalidConfigurationException::invalidValue(
                    "modalities.{$section}.default_driver",
                    sprintf('Class [%s] must implement %s.', $driver, $contract),
                );
            }
        }
    }
}
