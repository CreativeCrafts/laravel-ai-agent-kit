<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\Redactor;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\Concerns\ExtractsRedactedKeys;

final readonly class PipelineStepStarted
{
    use ExtractsRedactedKeys;

    /**
     * @param list<string> $stateKeys
     * @param list<string> $metadataKeys
     */
    public function __construct(
        public string $runId,
        public string $stepClass,
        public int $stepIndex,
        public ?string $selectedProvider,
        public array $stateKeys,
        public array $metadataKeys,
    ) {
    }

    public static function fromContext(RunContext $context, string $stepClass, int $stepIndex, ?Redactor $redactor = null): self
    {
        return new self(
            runId: $context->runId,
            stepClass: $stepClass,
            stepIndex: $stepIndex,
            selectedProvider: $context->selectedProvider,
            stateKeys: self::keys($context->state, $redactor),
            metadataKeys: self::keys($context->metadata, $redactor),
        );
    }

}
