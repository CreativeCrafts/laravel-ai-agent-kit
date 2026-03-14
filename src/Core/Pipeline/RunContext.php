<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Pipeline;

use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;

final readonly class RunContext
{
    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $state
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $runId,
        public array $input = [],
        public array $state = [],
        public array $metadata = [],
        public int $stepCount = 0,
        public int $toolCallCount = 0,
        public ?string $selectedProvider = null,
        public ?ConversationId $conversationId = null,
        public ?Conversation $conversation = null,
        public bool $storeConversation = true,
        public bool $continueConversation = false,
    ) {
    }

    public function hasInputValue(string $key): bool
    {
        return array_key_exists($key, $this->input);
    }

    public function inputValue(string $key, mixed $default = null): mixed
    {
        return $this->input[$key] ?? $default;
    }

    public function hasStateValue(string $key): bool
    {
        return array_key_exists($key, $this->state);
    }

    public function stateValue(string $key, mixed $default = null): mixed
    {
        return $this->state[$key] ?? $default;
    }

    public function metadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function hasConversationId(): bool
    {
        return $this->conversationId instanceof ConversationId;
    }

    public function hasConversation(): bool
    {
        return $this->conversation instanceof Conversation;
    }

    public function shouldStoreConversation(): bool
    {
        return $this->storeConversation;
    }

    public function shouldContinueConversation(): bool
    {
        return $this->continueConversation;
    }

    public function withStateValue(string $key, mixed $value): self
    {
        $state = $this->state;
        $state[$key] = $value;

        return $this->withState($state);
    }

    /**
     * @param array<string, mixed> $state
     */
    public function withState(array $state): self
    {
        return new self(
            runId: $this->runId,
            input: $this->input,
            state: $state,
            metadata: $this->metadata,
            stepCount: $this->stepCount,
            toolCallCount: $this->toolCallCount,
            selectedProvider: $this->selectedProvider,
            conversationId: $this->conversationId,
            conversation: $this->conversation,
            storeConversation: $this->storeConversation,
            continueConversation: $this->continueConversation,
        );
    }

    public function withMetadataValue(string $key, mixed $value): self
    {
        $metadata = $this->metadata;
        $metadata[$key] = $value;

        return $this->withMetadata($metadata);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            runId: $this->runId,
            input: $this->input,
            state: $this->state,
            metadata: $metadata,
            stepCount: $this->stepCount,
            toolCallCount: $this->toolCallCount,
            selectedProvider: $this->selectedProvider,
            conversationId: $this->conversationId,
            conversation: $this->conversation,
            storeConversation: $this->storeConversation,
            continueConversation: $this->continueConversation,
        );
    }

    public function withSelectedProvider(?string $selectedProvider): self
    {
        return new self(
            runId: $this->runId,
            input: $this->input,
            state: $this->state,
            metadata: $this->metadata,
            stepCount: $this->stepCount,
            toolCallCount: $this->toolCallCount,
            selectedProvider: $selectedProvider,
            conversationId: $this->conversationId,
            conversation: $this->conversation,
            storeConversation: $this->storeConversation,
            continueConversation: $this->continueConversation,
        );
    }

    public function withConversation(?Conversation $conversation): self
    {
        return new self(
            runId: $this->runId,
            input: $this->input,
            state: $this->state,
            metadata: $this->metadata,
            stepCount: $this->stepCount,
            toolCallCount: $this->toolCallCount,
            selectedProvider: $this->selectedProvider,
            conversationId: $conversation->id ?? $this->conversationId,
            conversation: $conversation,
            storeConversation: $this->storeConversation,
            continueConversation: $this->continueConversation,
        );
    }

    public function withConversationId(?ConversationId $conversationId): self
    {
        return new self(
            runId: $this->runId,
            input: $this->input,
            state: $this->state,
            metadata: $this->metadata,
            stepCount: $this->stepCount,
            toolCallCount: $this->toolCallCount,
            selectedProvider: $this->selectedProvider,
            conversationId: $conversationId,
            conversation: $this->conversation,
            storeConversation: $this->storeConversation,
            continueConversation: $this->continueConversation,
        );
    }

    public function forNewConversation(
        ConversationId $conversationId,
        bool $storeConversation = true,
    ): self {
        return new self(
            runId: $this->runId,
            input: $this->input,
            state: $this->state,
            metadata: $this->metadata,
            stepCount: $this->stepCount,
            toolCallCount: $this->toolCallCount,
            selectedProvider: $this->selectedProvider,
            conversationId: $conversationId,
            conversation: null,
            storeConversation: $storeConversation,
            continueConversation: false,
        );
    }

    public function forExistingConversation(
        ConversationId $conversationId,
        bool $storeConversation = true,
    ): self {
        return new self(
            runId: $this->runId,
            input: $this->input,
            state: $this->state,
            metadata: $this->metadata,
            stepCount: $this->stepCount,
            toolCallCount: $this->toolCallCount,
            selectedProvider: $this->selectedProvider,
            conversationId: $conversationId,
            conversation: null,
            storeConversation: $storeConversation,
            continueConversation: true,
        );
    }

    public function withoutConversationStorage(): self
    {
        return new self(
            runId: $this->runId,
            input: $this->input,
            state: $this->state,
            metadata: $this->metadata,
            stepCount: $this->stepCount,
            toolCallCount: $this->toolCallCount,
            selectedProvider: $this->selectedProvider,
            conversationId: $this->conversationId,
            conversation: $this->conversation,
            storeConversation: false,
            continueConversation: $this->continueConversation,
        );
    }

    public function incrementStepCount(int $by = 1): self
    {
        return new self(
            runId: $this->runId,
            input: $this->input,
            state: $this->state,
            metadata: $this->metadata,
            stepCount: $this->stepCount + $by,
            toolCallCount: $this->toolCallCount,
            selectedProvider: $this->selectedProvider,
            conversationId: $this->conversationId,
            conversation: $this->conversation,
            storeConversation: $this->storeConversation,
            continueConversation: $this->continueConversation,
        );
    }

    public function incrementToolCallCount(int $by = 1): self
    {
        return new self(
            runId: $this->runId,
            input: $this->input,
            state: $this->state,
            metadata: $this->metadata,
            stepCount: $this->stepCount,
            toolCallCount: $this->toolCallCount + $by,
            selectedProvider: $this->selectedProvider,
            conversationId: $this->conversationId,
            conversation: $this->conversation,
            storeConversation: $this->storeConversation,
            continueConversation: $this->continueConversation,
        );
    }
}
