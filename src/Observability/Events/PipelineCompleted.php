<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;

final readonly class PipelineCompleted
{
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

    public static function fromContext(RunContext $context): self
    {
        return new self(
            runId: $context->runId,
            totalSteps: $context->stepCount,
            toolCallCount: $context->toolCallCount,
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
