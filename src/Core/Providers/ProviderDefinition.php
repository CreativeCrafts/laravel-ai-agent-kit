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
        public ?string $sdkProvider = null,
    ) {
    }

    /**
     * Named Laravel AI provider instance for this profile.
     *
     * When `sdk_provider` is omitted, the Agent Kit driver is used so existing
     * profiles keep working when the driver name matches a Laravel AI provider.
     */
    public function sdkProviderName(): string
    {
        if ($this->sdkProvider !== null && $this->sdkProvider !== '') {
            return $this->sdkProvider;
        }

        return $this->driver;
    }

    public function model(): ?string
    {
        $model = $this->options['model'] ?? null;

        return is_string($model) && $model !== '' ? $model : null;
    }

    /**
     * Profile-default provider-native options from `options.provider_options`.
     *
     * These are not Laravel AI typed generation controls. They are merged into
     * the raw provider-options channel for the current attempt only.
     *
     * @return array<string, mixed>
     */
    public function providerOptions(): array
    {
        $options = $this->options['provider_options'] ?? [];

        if (!is_array($options)) {
            return [];
        }

        $normalized = [];

        foreach ($options as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
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
