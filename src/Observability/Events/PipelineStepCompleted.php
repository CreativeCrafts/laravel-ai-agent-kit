<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\Redactor;
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

    public static function fromContext(RunContext $context, string $stepClass, int $stepIndex, ?Redactor $redactor = null): self
    {
        return new self(
            runId: $context->runId,
            stepClass: $stepClass,
            stepIndex: $stepIndex,
            stepCount: $context->stepCount,
            selectedProvider: $context->selectedProvider,
            stateKeys: self::keys($context->state, $redactor),
            metadataKeys: self::keys($context->metadata, $redactor),
        );
    }

    /**
     * @param array<string, mixed> $values
     * @return list<string>
     */
    private static function keys(array $values, ?Redactor $redactor = null): array
    {
        if ($redactor instanceof Redactor) {
            return $redactor->redactKeys($values);
        }

        return array_values(
            array_filter(
                array_keys($values),
                static fn (string $key): bool => $key !== '',
            ),
        );
    }
}
