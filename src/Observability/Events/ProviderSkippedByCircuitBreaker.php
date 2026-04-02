<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

final readonly class ProviderSkippedByCircuitBreaker
{
    public function __construct(
        public string $provider,
        public string $state,
        public int $consecutiveFailures,
    ) {
    }
}
