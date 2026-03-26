<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Orchestration;

use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use InvalidArgumentException;

final readonly class OrchestrationRequest
{
    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $entryAgent,
        public string $task,
        public array $input = [],
        public array $metadata = [],
        public ?ConversationId $conversationId = null,
    ) {
        if ($this->entryAgent === '') {
            throw new InvalidArgumentException('Orchestration requests require a non-empty entryAgent.');
        }

        if ($this->task === '') {
            throw new InvalidArgumentException('Orchestration requests require a non-empty task.');
        }
    }

    public function inputValue(string $key, mixed $default = null): mixed
    {
        return $this->input[$key] ?? $default;
    }

    public function metadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
}
