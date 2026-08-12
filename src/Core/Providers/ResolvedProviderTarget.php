<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Providers;

/**
 * A resolved mapping from an Agent Kit provider profile (or a direct Laravel AI
 * provider name) to the identities the runtime and SDK bridge must keep separate.
 *
 * - profileName: Agent Kit policy identity (failover, circuit breaker, telemetry)
 * - sdkProviderName: named Laravel AI provider instance
 * - driver: underlying Laravel AI / provider driver
 */
final readonly class ResolvedProviderTarget
{
    /**
     * @param array<string, mixed> $providerOptions
     */
    public function __construct(
        public ?string $profileName,
        public ?string $sdkProviderName,
        public ?string $driver,
        public ?string $model,
        public array $providerOptions = [],
    ) {
    }

    /**
     * Identity used for Agent Kit policy: failover traversal, circuit breakers,
     * and profile-oriented telemetry. Falls back to the SDK provider name for
     * direct-SDK compatibility, then to "default".
     */
    public function policyIdentity(): string
    {
        if ($this->profileName !== null && $this->profileName !== '') {
            return $this->profileName;
        }

        if ($this->sdkProviderName !== null && $this->sdkProviderName !== '') {
            return $this->sdkProviderName;
        }

        return 'default';
    }
}
