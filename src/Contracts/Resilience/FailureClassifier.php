<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience;

use CreativeCrafts\LaravelAiAgentKit\Resilience\FailureDisposition;
use Throwable;

interface FailureClassifier
{
    /** Classify one failure for telemetry, health, retry, and failover decisions. */
    public function classify(Throwable $throwable): FailureDisposition;
}
