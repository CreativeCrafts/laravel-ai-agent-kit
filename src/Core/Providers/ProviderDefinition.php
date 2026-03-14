<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Providers;

final readonly class ProviderDefinition
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public string $name,
        public string $driver,
        public bool $enabled = true,
        public array $options = [],
    ) {
    }
}
