<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Agents;

use InvalidArgumentException;

final readonly class AgentDefinition
{
    /**
     * @param list<string> $requiredCapabilities
     * @param list<string> $fallbackProviderProfiles
     * @param list<string> $delegationTargets
     */
    public function __construct(
        public string $key,
        public string $displayName,
        public array $requiredCapabilities,
        public string $primaryProviderProfile,
        public array $fallbackProviderProfiles = [],
        public array $delegationTargets = [],
    ) {
        if ($this->key === '') {
            throw new InvalidArgumentException('Agent definitions require a non-empty key.');
        }

        if ($this->displayName === '') {
            throw new InvalidArgumentException('Agent definitions require a non-empty displayName.');
        }

        if ($this->primaryProviderProfile === '') {
            throw new InvalidArgumentException('Agent definitions require a non-empty primaryProviderProfile.');
        }

        $this->assertUniqueNonEmptyList($this->requiredCapabilities, 'requiredCapabilities');
        $this->assertUniqueNonEmptyList($this->fallbackProviderProfiles, 'fallbackProviderProfiles');
        $this->assertUniqueNonEmptyList($this->delegationTargets, 'delegationTargets');

        if (in_array($this->primaryProviderProfile, $this->fallbackProviderProfiles, true)) {
            throw new InvalidArgumentException('Agent definition fallbackProviderProfiles must not include the primaryProviderProfile.');
        }
    }

    /**
     * @return list<string>
     */
    public function providerProfiles(): array
    {
        return [$this->primaryProviderProfile, ...$this->fallbackProviderProfiles];
    }

    public function requiresCapability(string $capability): bool
    {
        return in_array($capability, $this->requiredCapabilities, true);
    }

    public function allowsDelegationTo(string $agentKey): bool
    {
        return in_array($agentKey, $this->delegationTargets, true);
    }

    /**
     * @param list<string> $values
     */
    private function assertUniqueNonEmptyList(array $values, string $field): void
    {
        foreach ($values as $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('Agent definition %s entries must be non-empty strings.', $field));
            }
        }

        if (count(array_unique($values)) !== count($values)) {
            throw new InvalidArgumentException(sprintf('Agent definition %s entries must be unique.', $field));
        }
    }
}
