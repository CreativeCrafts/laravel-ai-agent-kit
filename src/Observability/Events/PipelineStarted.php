<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\Redactor;
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

    public static function fromContext(RunContext $context, int $totalSteps, ?Redactor $redactor = null): self
    {
        return new self(
            runId: $context->runId,
            totalSteps: $totalSteps,
            selectedProvider: $context->selectedProvider,
            inputKeys: self::keys($context->input, $redactor),
            metadataKeys: self::keys($context->metadata, $redactor),
            storeConversation: $context->storeConversation,
            continueConversation: $context->continueConversation,
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
