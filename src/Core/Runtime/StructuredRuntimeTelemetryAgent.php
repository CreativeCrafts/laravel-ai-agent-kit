<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use Closure;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\StructuredAnonymousAgent;

final class StructuredRuntimeTelemetryAgent extends StructuredAnonymousAgent implements CarriesRuntimeTelemetry, HasProviderOptions
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
        Closure $schema,
        ?GenerationOptions $generationOptions = null,
    ) {
        $this->telemetryContext = $telemetryContext;
        $this->generationOptions = $generationOptions;

        parent::__construct($instructions, $messages, $tools, $schema);
    }
}
