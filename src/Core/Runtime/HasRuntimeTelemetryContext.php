<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

trait HasRuntimeTelemetryContext
{
    public readonly RuntimeTelemetryContext $telemetryContext;

    public function telemetryContext(): RuntimeTelemetryContext
    {
        return $this->telemetryContext;
    }
}
