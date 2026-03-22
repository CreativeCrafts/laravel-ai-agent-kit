<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

final readonly class RuntimeExecutionStarted
{
    /**
     * @param list<string> $requestedToolNames
     * @param list<string> $inputKeys
     * @param list<string> $metadataKeys
     */
    public function __construct(
        public string $runId,
        public string $invocationId,
        public string $provider,
        public string $model,
        public array $requestedToolNames,
        public array $inputKeys,
        public array $metadataKeys,
        public ?string $packageConversationId,
        public bool $storeConversation,
        public bool $continueConversation,
        public int $projectedMessageCount,
        public int $promptLength,
        public int $attachmentCount,
        public ?int $timeout,
    ) {
    }
}
