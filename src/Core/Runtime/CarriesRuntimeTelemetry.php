<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

/**
 * Marker interface applied to kit-owned SDK agent subclasses so the
 * SdkTelemetryNormalizer can recognize them regardless of which concrete
 * SDK agent class they extend (AnonymousAgent vs StructuredAnonymousAgent).
 */
interface CarriesRuntimeTelemetry
{
    public function telemetryContext(): RuntimeTelemetryContext;
}
