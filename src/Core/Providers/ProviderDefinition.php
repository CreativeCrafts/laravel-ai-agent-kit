<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Providers;

final readonly class ProviderDefinition
{
    /**
     * @param array<string, mixed> $options
     * @param list<string> $capabilities
     */
    public function __construct(
        public string $name,
        public string $driver,
        public bool $enabled = true,
        public array $options = [],
        public array $capabilities = [],
    ) {
    }

    public function supportsCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    /**
     * @param list<string> $requiredCapabilities
     * @return list<string>
     */
    public function missingCapabilities(array $requiredCapabilities): array
    {
        $missing = [];

        foreach ($requiredCapabilities as $capability) {
            if (!in_array($capability, $this->capabilities, true)) {
                $missing[] = $capability;
            }
        }

        return $missing;
    }
}
