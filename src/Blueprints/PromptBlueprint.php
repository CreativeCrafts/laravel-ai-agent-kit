<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints;

use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use InvalidArgumentException;

final readonly class PromptBlueprint
{
    /**
     * @param array<string, scalar|null> $variables
     * @param list<string> $instructions
     * @param list<string> $toolNames
     * @param array<string, mixed> $input
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $promptName,
        public ?string $runId = null,
        public ?string $version = null,
        public array $variables = [],
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
        if ($this->promptName === '') {
            throw new InvalidArgumentException('Prompt blueprints require a non-empty prompt name.');
        }

        foreach (
          [
            'runId' => $this->runId,
            'version' => $this->version,
            'provider' => $this->provider,
            'model' => $this->model,
          ] as $field => $value
        ) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('Prompt blueprint [%s] must be null or a non-empty string.', $field));
            }
        }

        if ($this->timeout !== null && $this->timeout < 1) {
            throw new InvalidArgumentException('Prompt blueprints require timeout to be null or an integer greater than or equal to 1.');
        }

        if ($this->continueConversation && !$this->conversationId instanceof ConversationId) {
            throw new InvalidArgumentException('Prompt blueprints that continue a conversation require a conversationId.');
        }
    }

    public static function forPrompt(string $promptName): self
    {
        return new self(promptName: $promptName);
    }

    public function withRunId(string $runId): self
    {
        return new self(
            promptName: $this->promptName,
            runId: $runId,
            version: $this->version,
            variables: $this->variables,
            instructions: $this->instructions,
            provider: $this->provider,
            model: $this->model,
            toolNames: $this->toolNames,
            input: $this->input,
            metadata: $this->metadata,
            timeout: $this->timeout,
            conversationId: $this->conversationId,
            storeConversation: $this->storeConversation,
            continueConversation: $this->continueConversation,
        );
    }

    public function withVersion(?string $version): self
    {
        return new self(
            promptName: $this->promptName,
            runId: $this->runId,
            version: $version,
            variables: $this->variables,
            instructions: $this->instructions,
            provider: $this->provider,
            model: $this->model,
            toolNames: $this->toolNames,
            input: $this->input,
            metadata: $this->metadata,
            timeout: $this->timeout,
            conversationId: $this->conversationId,
            storeConversation: $this->storeConversation,
            continueConversation: $this->continueConversation,
        );
    }

    public function withVariable(string $key, string|int|float|bool|null $value): self
    {
        $variables = $this->variables;
        $variables[$key] = $value;

        return $this->withVariables($variables);
    }

    /**
     * @param array<string, scalar|null> $variables
     */
    public function withVariables(array $variables): self
    {
        return new self(
            promptName: $this->promptName,
            runId: $this->runId,
            version: $this->version,
            variables: $variables,
            instructions: $this->instructions,
            provider: $this->provider,
            model: $this->model,
            toolNames: $this->toolNames,
            input: $this->input,
            metadata: $this->metadata,
            timeout: $this->timeout,
            conversationId: $this->conversationId,
            storeConversation: $this->storeConversation,
            continueConversation: $this->continueConversation,
        );
    }

    public function addInstruction(string $instruction): self
    {
        $instructions = $this->instructions;
        $instructions[] = $instruction;

        return $this->withInstructions($instructions);
    }

    /**
     * @param list<string> $instructions
     */
    public function withInstructions(array $instructions): self
    {
        return new self(
            promptName: $this->promptName,
            runId: $this->runId,
            version: $this->version,
            variables: $this->variables,
            instructions: $this->normalizeStringList($instructions),
            provider: $this->provider,
            model: $this->model,
            toolNames: $this->toolNames,
            input: $this->input,
            metadata: $this->metadata,
            timeout: $this->timeout,
            conversationId: $this->conversationId,
            storeConversation: $this->storeConversation,
            continueConversation: $this->continueConversation,
        );
    }

    public function usingProvider(?string $provider): self
    {
        return new self(
            promptName: $this->promptName,
            runId: $this->runId,
            version: $this->version,
            variables: $this->variables,
            instructions: $this->instructions,
            provider: $provider,
            model: $this->model,
            toolNames: $this->toolNames,
            input: $this->input,
            metadata: $this->metadata,
            timeout: $this->timeout,
            conversationId: $this->conversationId,
            storeConversation: $this->storeConversation,
            continueConversation: $this->continueConversation,
        );
    }

    public function usingModel(?string $model): self
    {
        return new self(
            promptName: $this->promptName,
            runId: $this->runId,
            version: $this->version,
            variables: $this->variables,
            instructions: $this->instructions,
            provider: $this->provider,
            model: $model,
            toolNames: $this->toolNames,
            input: $this->input,
            metadata: $this->metadata,
            timeout: $this->timeout,
            conversationId: $this->conversationId,
            storeConversation: $this->storeConversation,
            continueConversation: $this->continueConversation,
        );
    }

    public function addTool(string $toolName): self
    {
        $toolNames = $this->toolNames;
        $toolNames[] = $toolName;

        return $this->withTools($toolNames);
    }

    /**
     * @param list<string> $toolNames
     */
    public function withTools(array $toolNames): self
    {
        return new self(
            promptName: $this->promptName,
            runId: $this->runId,
            version: $this->version,
            variables: $this->variables,
            instructions: $this->instructions,
            provider: $this->provider,
            model: $this->model,
            toolNames: $this->normalizeStringList($toolNames),
            input: $this->input,
            metadata: $this->metadata,
            timeout: $this->timeout,
            conversationId: $this->conversationId,
            storeConversation: $this->storeConversation,
            continueConversation: $this->continueConversation,
        );
    }

    public function withInputValue(string $key, mixed $value): self
    {
        $input = $this->input;
        $input[$key] = $value;

        return $this->withInput($input);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function withInput(array $input): self
    {
        return new self(
            promptName: $this->promptName,
            runId: $this->runId,
            version: $this->version,
            variables: $this->variables,
            instructions: $this->instructions,
            provider: $this->provider,
            model: $this->model,
            toolNames: $this->toolNames,
            input: $input,
            metadata: $this->metadata,
            timeout: $this->timeout,
            conversationId: $this->conversationId,
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
            promptName: $this->promptName,
            runId: $this->runId,
            version: $this->version,
            variables: $this->variables,
            instructions: $this->instructions,
            provider: $this->provider,
            model: $this->model,
            toolNames: $this->toolNames,
            input: $this->input,
            metadata: $metadata,
            timeout: $this->timeout,
            conversationId: $this->conversationId,
            storeConversation: $this->storeConversation,
            continueConversation: $this->continueConversation,
        );
    }

    public function withTimeout(?int $timeout): self
    {
        return new self(
            promptName: $this->promptName,
            runId: $this->runId,
            version: $this->version,
            variables: $this->variables,
            instructions: $this->instructions,
            provider: $this->provider,
            model: $this->model,
            toolNames: $this->toolNames,
            input: $this->input,
            metadata: $this->metadata,
            timeout: $timeout,
            conversationId: $this->conversationId,
            storeConversation: $this->storeConversation,
            continueConversation: $this->continueConversation,
        );
    }

    public function startConversation(ConversationId|string|null $conversationId = null, bool $storeConversation = true): self
    {
        return new self(
            promptName: $this->promptName,
            runId: $this->runId,
            version: $this->version,
            variables: $this->variables,
            instructions: $this->instructions,
            provider: $this->provider,
            model: $this->model,
            toolNames: $this->toolNames,
            input: $this->input,
            metadata: $this->metadata,
            timeout: $this->timeout,
            conversationId: $this->resolveConversationId($conversationId),
            storeConversation: $storeConversation,
            continueConversation: false,
        );
    }

    public function continueConversation(ConversationId|string $conversationId, bool $storeConversation = true): self
    {
        return new self(
            promptName: $this->promptName,
            runId: $this->runId,
            version: $this->version,
            variables: $this->variables,
            instructions: $this->instructions,
            provider: $this->provider,
            model: $this->model,
            toolNames: $this->toolNames,
            input: $this->input,
            metadata: $this->metadata,
            timeout: $this->timeout,
            conversationId: $this->resolveConversationId($conversationId),
            storeConversation: $storeConversation,
            continueConversation: true,
        );
    }

    public function withoutConversationContext(): self
    {
        return new self(
            promptName: $this->promptName,
            runId: $this->runId,
            version: $this->version,
            variables: $this->variables,
            instructions: $this->instructions,
            provider: $this->provider,
            model: $this->model,
            toolNames: $this->toolNames,
            input: $this->input,
            metadata: $this->metadata,
            timeout: $this->timeout,
            conversationId: null,
            storeConversation: false,
            continueConversation: false,
        );
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private function normalizeStringList(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            if ($value === '') {
                continue;
            }
            if (in_array($value, $normalized, true)) {
                continue;
            }
            $normalized[] = $value;
        }

        return $normalized;
    }

    private function resolveConversationId(ConversationId|string|null $conversationId): ?ConversationId
    {
        if ($conversationId === null) {
            return null;
        }

        return $conversationId instanceof ConversationId
          ? $conversationId
          : new ConversationId($conversationId);
    }
}
