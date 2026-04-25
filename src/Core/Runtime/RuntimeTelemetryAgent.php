<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Contracts\HasProviderOptions;

final class RuntimeTelemetryAgent extends AnonymousAgent implements CarriesRuntimeTelemetry, HasProviderOptions
{
    use CarriesGenerationOptions;
    use HasRuntimeTelemetryContext;

    /**
     * @param iterable<mixed> $messages
     * @param iterable<mixed> $tools
     */
    public function __construct(
        RuntimeTelemetryContext $telemetryContext,
        string $instructions,
        iterable $messages,
        iterable $tools,
        ?GenerationOptions $generationOptions = null,
    ) {
        $this->telemetryContext = $telemetryContext;
        $this->generationOptions = $generationOptions;

        parent::__construct($instructions, $messages, $tools);
    }
}
