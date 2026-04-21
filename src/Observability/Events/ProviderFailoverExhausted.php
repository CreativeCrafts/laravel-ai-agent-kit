<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderDefinition;

final readonly class ProviderFailoverExhausted
{
    /**
     * @param list<string> $orderedProviders
     */
    public function __construct(
        public string $currentProvider,
        public array $orderedProviders,
    ) {
    }

    /**
     * @param list<ProviderDefinition> $orderedProviders
     */
    public static function fromDefinitions(string $currentProvider, array $orderedProviders): self
    {
        return new self(
            currentProvider: $currentProvider,
            orderedProviders: array_map(
                static fn (ProviderDefinition $provider): string => $provider->name,
                $orderedProviders,
            ),
        );
    }
}
