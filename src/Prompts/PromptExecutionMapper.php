<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Prompts;

use Closure;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Prompts\PromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\GenerationOptions;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use InvalidArgumentException;
use Laravel\Ai\Files\File;
use Laravel\Ai\ObjectSchema;

final readonly class PromptExecutionMapper
{
    public function __construct(
        private PromptRepository $promptRepository,
    ) {
    }

    /**
     * @param array<string, scalar|null> $variables
     * @param list<string> $instructions
     * @param list<string> $toolNames
     * @param list<string> $providerToolNames
     * @param list<File> $attachments
     * @param array<string, mixed> $input
     * @param array<string, mixed> $metadata
     */
    public function mapToExecutionRequest(
        string $name,
        string $runId,
        array $variables = [],
        ?string $version = null,
        array $instructions = [],
        ?string $provider = null,
        ?string $model = null,
        array $toolNames = [],
        array $input = [],
        array $metadata = [],
        ?int $timeout = null,
        ?ConversationId $conversationId = null,
        bool $storeConversation = false,
        bool $continueConversation = false,
        ?GenerationOptions $generationOptions = null,
        Closure|ObjectSchema|string|null $schema = null,
        array $attachments = [],
        array $providerToolNames = [],
        bool $strictStructuredOutput = false,
    ): ExecutionRequest {
        $template = $this->promptRepository->get($name, $version);
        $renderedPrompt = $template->render($variables);

        /** @var array<string, mixed> $resolvedMetadata */
        $resolvedMetadata = array_merge($metadata, [
          'prompt_name' => $template->name,
          'prompt_version' => $template->version,
        ]);

        /** @var list<string> $resolvedInstructions */
        $resolvedInstructions = array_values(
            array_filter(
                $instructions,
                static fn (string $instruction): bool => $instruction !== '',
            ),
        );

        return new ExecutionRequest(
            runId: $runId,
            prompt: $renderedPrompt,
            instructions: $resolvedInstructions,
            provider: $provider,
            model: $model,
            toolNames: $toolNames,
            input: $input,
            metadata: $resolvedMetadata,
            timeout: $timeout,
            conversationId: $conversationId,
            storeConversation: $storeConversation,
            continueConversation: $continueConversation,
            generationOptions: $generationOptions,
            schema: $schema,
            attachments: $attachments,
            providerToolNames: $providerToolNames,
            strictStructuredOutput: $strictStructuredOutput,
        );
    }

    public function normalizeSchema(mixed $schema): Closure|ObjectSchema|string|null
    {
        if ($schema === null || $schema instanceof Closure || $schema instanceof ObjectSchema) {
            return $schema;
        }

        if (is_string($schema) && $schema !== '') {
            return $schema;
        }

        throw new InvalidArgumentException(
            'schema must be null, a Closure, an ObjectSchema, or a non-empty class-string.',
        );
    }
}
