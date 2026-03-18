<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderDefinition;

final readonly class ProviderFailoverResolved
{
    /**
     * @param list<string> $orderedProviders
     */
    public function __construct(
        public string $currentProvider,
        public ?string $nextProvider,
        public array $orderedProviders,
    ) {
    }

    /**
     * @param list<ProviderDefinition> $orderedProviders
     */
    public static function fromDefinitions(string $currentProvider, ?ProviderDefinition $nextProvider, array $orderedProviders): self
    {
        return new self(
            currentProvider: $currentProvider,
            nextProvider: $nextProvider?->name,
            orderedProviders: array_map(
                static fn (ProviderDefinition $provider): string => $provider->name,
                $orderedProviders,
            ),
        );
    }
}
