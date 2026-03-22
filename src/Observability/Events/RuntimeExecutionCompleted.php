<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

final readonly class RuntimeExecutionCompleted
{
    /**
     * @param list<string> $requestedToolNames
     */
    public function __construct(
        public string $runId,
        public string $invocationId,
        public string $provider,
        public string $model,
        public array $requestedToolNames,
        public ?string $packageConversationId,
        public ?string $sdkConversationId,
        public int $projectedMessageCount,
        public int $promptTokens,
        public int $completionTokens,
        public int $totalTokens,
        public int $outputLength,
        public int $messageCount,
        public int $toolCallCount,
        public int $toolResultCount,
        public int $stepCount,
    ) {
    }
}
