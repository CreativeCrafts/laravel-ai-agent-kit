<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;

final readonly class PipelineStepCompleted
{
    /**
     * @param list<string> $stateKeys
     * @param list<string> $metadataKeys
     */
    public function __construct(
        public string $runId,
        public string $stepClass,
        public int $stepIndex,
        public int $stepCount,
        public ?string $selectedProvider,
        public array $stateKeys,
        public array $metadataKeys,
    ) {
    }

    public static function fromContext(RunContext $context, string $stepClass, int $stepIndex): self
    {
        return new self(
            runId: $context->runId,
            stepClass: $stepClass,
            stepIndex: $stepIndex,
            stepCount: $context->stepCount,
            selectedProvider: $context->selectedProvider,
            stateKeys: self::keys($context->state),
            metadataKeys: self::keys($context->metadata),
        );
    }

    /**
     * @param array<string, mixed> $values
     * @return list<string>
     */
    private static function keys(array $values): array
    {
        return array_values(
            array_filter(
                array_keys($values),
                static fn (string $key): bool => $key !== '',
            ),
        );
    }
}
