<?php

namespace CreativeCrafts\LaravelAiAgentKit\Core\Pipeline;

final readonly class RunContext
{
    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $runId,
        public array $input = [],
        public array $state = [],
        public array $metadata = [],
        public int $stepCount = 0,
        public int $toolCallCount = 0,
        public ?string $selectedProvider = null,
    ) {}

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

    public function withStateValue(string $key, mixed $value): self
    {
        $state = $this->state;
        $state[$key] = $value;

        return $this->withState($state);
    }

    /**
     * @param  array<string, mixed>  $state
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
        );
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
            runId: $this->runId,
            input: $this->input,
            state: $this->state,
            metadata: $metadata,
            stepCount: $this->stepCount,
            toolCallCount: $this->toolCallCount,
            selectedProvider: $this->selectedProvider,
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
        );
    }
}
