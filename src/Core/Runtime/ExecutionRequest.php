<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use InvalidArgumentException;

final readonly class ExecutionRequest
{
    /**
     * @param list<string> $instructions
     * @param list<string> $toolNames
     * @param array<string, mixed> $input
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $runId,
        public string $prompt,
        public array $instructions = [],
        public ?string $provider = null,
        public ?string $model = null,
        public array $toolNames = [],
        public array $input = [],
        public array $metadata = [],
        public ?int $timeout = null,
        public ?ConversationId $conversationId = null,
        public bool $storeConversation = false,
        public bool $continueConversation = false,
    ) {
        if ($this->runId === '') {
            throw new InvalidArgumentException('Execution requests require a non-empty runId.');
        }

        if ($this->prompt === '') {
            throw new InvalidArgumentException('Execution requests require a non-empty prompt.');
        }

        if ($this->timeout !== null && $this->timeout < 1) {
            throw new InvalidArgumentException('Execution request timeout must be null or an integer greater than or equal to 1.');
        }

        if ($this->continueConversation && !$this->conversationId instanceof ConversationId) {
            throw new InvalidArgumentException('Execution requests that continue a conversation require a conversationId.');
        }
    }
}
