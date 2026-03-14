<?php

namespace CreativeCrafts\LaravelAiAgentKit\Core\Pipeline;

final readonly class QueueDispatchOptions
{
    public function __construct(
        public ?string $connection = null,
        public ?string $queue = null,
        public ?int $delaySeconds = null,
        public ?int $timeoutSeconds = null,
    ) {}
}
