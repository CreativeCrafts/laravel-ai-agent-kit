<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\Redactor;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\Concerns\ExtractsRedactedKeys;

final readonly class PipelineCompleted
{
    use ExtractsRedactedKeys;

    /**
     * @param list<string> $stateKeys
     * @param list<string> $metadataKeys
     */
    public function __construct(
        public string $runId,
        public int $totalSteps,
        public int $toolCallCount,
        public ?string $selectedProvider,
        public array $stateKeys,
        public array $metadataKeys,
    ) {
    }

    public static function fromContext(RunContext $context, ?Redactor $redactor = null): self
    {
        return new self(
            runId: $context->runId,
            totalSteps: $context->stepCount,
            toolCallCount: $context->toolCallCount,
            selectedProvider: $context->selectedProvider,
            stateKeys: self::keys($context->state, $redactor),
            metadataKeys: self::keys($context->metadata, $redactor),
        );
    }

}
