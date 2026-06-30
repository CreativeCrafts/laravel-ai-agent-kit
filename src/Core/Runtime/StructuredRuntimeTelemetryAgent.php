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
        string $instructions,
        iterable $messages,
        iterable $tools,
        ?Closure $schema = null,
        ?RuntimeTelemetryContext $telemetryContext = null,
        ?GenerationOptions $generationOptions = null,
    ) {
        parent::__construct($instructions, $messages, $tools, $schema);

        $this->telemetryContext = $telemetryContext ?? new RuntimeTelemetryContext(
            runId: '',
            requestedToolNames: [],
            inputKeys: [],
            metadataKeys: [],
            packageConversationId: null,
            storeConversation: false,
            continueConversation: false,
            projectedMessageCount: 0,
        );
        $this->generationOptions = $generationOptions;
    }
}
