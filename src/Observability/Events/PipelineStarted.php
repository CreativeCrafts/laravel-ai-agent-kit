<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;

final readonly class PipelineStarted
{
    /**
     * @param list<string> $inputKeys
     * @param list<string> $metadataKeys
     */
    public function __construct(
        public string $runId,
        public int $totalSteps,
        public ?string $selectedProvider,
        public array $inputKeys,
        public array $metadataKeys,
        public bool $storeConversation,
        public bool $continueConversation,
    ) {
    }

    public static function fromContext(RunContext $context, int $totalSteps): self
    {
        return new self(
            runId: $context->runId,
            totalSteps: $totalSteps,
            selectedProvider: $context->selectedProvider,
            inputKeys: self::keys($context->input),
            metadataKeys: self::keys($context->metadata),
            storeConversation: $context->storeConversation,
            continueConversation: $context->continueConversation,
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
