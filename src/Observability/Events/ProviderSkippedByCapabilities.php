<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

final readonly class ProviderSkippedByCapabilities
{
    /**
     * @param list<string> $missingCapabilities
     */
    public function __construct(
        public string $provider,
        public string $providerSkippedReason,
        public array $missingCapabilities,
    ) {
    }
}
