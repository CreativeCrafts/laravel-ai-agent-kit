<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use Laravel\Ai\AnonymousAgent;

final class RuntimeTelemetryAgent extends AnonymousAgent
{
    /**
     * @param iterable<mixed> $messages
     * @param iterable<mixed> $tools
     */
    public function __construct(
        public readonly RuntimeTelemetryContext $telemetryContext,
        string $instructions,
        iterable $messages,
        iterable $tools,
    ) {
        parent::__construct($instructions, $messages, $tools);
    }
}
