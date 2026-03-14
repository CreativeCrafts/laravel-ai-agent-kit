<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Memory;

use DateTimeImmutable;

final readonly class ConversationMessage
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public MessageId $id,
        public ConversationMessageRole $role,
        public string $content,
        public DateTimeImmutable $createdAt,
        public array $metadata = [],
    ) {
    }

    public function hasMetadata(string $key): bool
    {
        return array_key_exists($key, $this->metadata);
    }

    public function metadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function withMetadataValue(string $key, mixed $value): self
    {
        $metadata = $this->metadata;
        $metadata[$key] = $value;

        return $this->withMetadata($metadata);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            id: $this->id,
            role: $this->role,
            content: $this->content,
            createdAt: $this->createdAt,
            metadata: $metadata,
        );
    }
}
