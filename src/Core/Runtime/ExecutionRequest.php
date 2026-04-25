<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use Closure;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use InvalidArgumentException;
use Laravel\Ai\Files\File;
use Laravel\Ai\ObjectSchema;

final readonly class ExecutionRequest
{
    /**
     * @param list<string> $instructions
     * @param list<string> $toolNames
     * @param list<string> $providerToolNames
     * @param list<File> $attachments
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
        public ?GenerationOptions $generationOptions = null,
        public Closure|ObjectSchema|string|null $schema = null,
        public array $attachments = [],
        public array $providerToolNames = [],
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

        if (is_string($this->schema)) {
            if ($this->schema === '') {
                throw new InvalidArgumentException('Execution request schema class-string must be non-empty.');
            }

            if (!class_exists($this->schema)) {
                throw new InvalidArgumentException(
                    sprintf('Execution request schema class-string [%s] does not exist.', $this->schema),
                );
            }
        }

        $this->validateAttachments($this->attachments);
        $this->validateProviderToolNames($this->providerToolNames);
    }

    /**
     * @param array<int, mixed> $attachments
     */
    private function validateAttachments(array $attachments): void
    {
        foreach ($attachments as $index => $attachment) {
            if (!$attachment instanceof File) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Execution request attachment at index [%d] must be an instance of [%s], got [%s].',
                        $index,
                        File::class,
                        get_debug_type($attachment),
                    ),
                );
            }
        }
    }

    /**
     * @param array<int, mixed> $providerToolNames
     */
    private function validateProviderToolNames(array $providerToolNames): void
    {
        foreach ($providerToolNames as $index => $name) {
            if (!is_string($name) || $name === '') {
                throw new InvalidArgumentException(
                    sprintf('Execution request providerToolNames[%d] must be a non-empty string.', $index),
                );
            }
        }
    }
}
