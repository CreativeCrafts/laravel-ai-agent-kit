<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Resilience;

use CreativeCrafts\LaravelAiAgentKit\Observability\Support\FailureCategory;

final readonly class FailureDisposition
{
    public function __construct(
        public FailureCategory $category,
        public bool $providerHealthFailure,
        public bool $retryable,
        public bool $failoverSafe,
        public string $reason,
    ) {
    }
}
