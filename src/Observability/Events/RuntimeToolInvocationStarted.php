<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

final readonly class RuntimeToolInvocationStarted
{
    /**
     * @param list<string> $argumentKeys
     */
    public function __construct(
        public string $runId,
        public string $invocationId,
        public string $toolInvocationId,
        public string $toolName,
        public array $argumentKeys,
        public ?string $packageConversationId,
    ) {
    }
}
