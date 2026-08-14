<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Memory;

use DateTimeImmutable;

final readonly class Conversation
{
    /**
     * @param  list<ConversationMessage>  $messages
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ConversationId $id,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public array $messages = [],
        public array $metadata = [],
        public int $revision = 0,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->messages === [];
    }

    public function messageCount(): int
    {
        return count($this->messages);
    }

    public function latestMessage(): ?ConversationMessage
    {
        return $this->messages === [] ? null : $this->messages[array_key_last($this->messages)];
    }

    public function hasMetadata(string $key): bool
    {
        return array_key_exists($key, $this->metadata);
    }

    public function metadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function withAppendedMessage(ConversationMessage $message, ?DateTimeImmutable $updatedAt = null): self
    {
        $messages = $this->messages;
        $messages[] = $message;

        return $this->withMessages($messages, $updatedAt ?? $message->createdAt);
    }

    /**
     * @param  list<ConversationMessage>  $messages
     */
    public function withMessages(array $messages, ?DateTimeImmutable $updatedAt = null): self
    {
        return new self(
            id: $this->id,
            createdAt: $this->createdAt,
            updatedAt: $updatedAt ?? $this->updatedAt,
            messages: $messages,
            metadata: $this->metadata,
            revision: $this->revision,
        );
    }

    public function withMetadataValue(string $key, mixed $value, ?DateTimeImmutable $updatedAt = null): self
    {
        $metadata = $this->metadata;
        $metadata[$key] = $value;

        return $this->withMetadata($metadata, $updatedAt);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata, ?DateTimeImmutable $updatedAt = null): self
    {
        return new self(
            id: $this->id,
            createdAt: $this->createdAt,
            updatedAt: $updatedAt ?? $this->updatedAt,
            messages: $this->messages,
            metadata: $metadata,
            revision: $this->revision,
        );
    }

    public function withRevision(int $revision): self
    {
        return new self(
            id: $this->id,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            messages: $this->messages,
            metadata: $this->metadata,
            revision: $revision,
        );
    }
}
