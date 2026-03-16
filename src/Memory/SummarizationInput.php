<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Memory;

final readonly class SummarizationInput
{
    /**
     * @param list<ConversationMessage> $messages
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public ConversationId $conversationId,
        public array $messages,
        public ?string $existingSummary = null,
        public array $metadata = [],
    ) {
    }

    public function messageCount(): int
    {
        return count($this->messages);
    }

    public function hasExistingSummary(): bool
    {
        return $this->existingSummary !== null && $this->existingSummary !== '';
    }

    public function metadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
}
