<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationContextManager;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\ConversationContextBridgeException;
use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessage;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessageRole;
use CreativeCrafts\LaravelAiAgentKit\Memory\MessageId;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\RuntimeAttachmentsReplayed;
use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use Laravel\Ai\Files\File;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\ToolResult;

final readonly class RuntimeConversationMemoryBridge
{
    public function __construct(
        private ConversationContextManager $conversationContextManager,
        private ConfigRepository $config,
        private ?Dispatcher $events = null,
    ) {
    }

    public function project(ExecutionRequest $request): ProjectedConversationContext
    {
        if (!$this->shouldBridge($request)) {
            return new ProjectedConversationContext(context: null);
        }

        $context = new RunContext(
            runId: $request->runId,
            input: $request->input,
            metadata: $request->metadata,
            conversationId: $request->conversationId,
            storeConversation: $request->storeConversation,
            continueConversation: $request->continueConversation,
        );

        if ($request->continueConversation) {
            $conversationId = $request->conversationId;

            if (!$conversationId instanceof ConversationId) {
                throw ConversationContextBridgeException::missingConversationIdForContinuation($request->runId);
            }

            $context = $this->conversationContextManager->continue(
                context: $context,
                conversationId: $conversationId,
                storeConversation: $request->storeConversation,
            );
        } else {
            $context = $this->conversationContextManager->start(
                context: $context,
                conversationId: $request->conversationId,
                storeConversation: $request->storeConversation,
            );
        }

        $conversation = $context->conversation;

        if (!$conversation instanceof Conversation) {
            return new ProjectedConversationContext(context: $context);
        }

        [$messages, $systemInstructions] = $this->projectConversation($conversation);

        $mergeMode = 'none';
        $priorIncluded = 0;
        $priorExcluded = 0;
        $priorExclusions = [];

        if ($request->continueConversation) {
            $replay = RuntimeAttachmentReplayResolver::resolveLastUserTurn($conversation, $this->attachmentReplayPolicy());
            $priorIncluded = count($replay->files);
            $priorExcluded = count($replay->exclusions);
            $priorExclusions = $replay->exclusions;

            if ($priorExcluded > 0 && $this->events instanceof Dispatcher) {
                $this->events->dispatch(new RuntimeAttachmentsReplayed(
                    runId: $request->runId,
                    excludedCount: $priorExcluded,
                    includedCount: $priorIncluded,
                    exclusions: $replay->exclusions,
                ));
            }

            $mode = $request->metadata['attachment_replay'] ?? 'none';
            $modeString = is_string($mode) ? $mode : 'none';

            if ($modeString === 'merge' && $replay->files !== []) {
                $mergeMode = 'merged';
            } elseif ($modeString === 'replay_only' && $replay->files !== []) {
                $mergeMode = 'replay_only';
            }

            return new ProjectedConversationContext(
                context: $context,
                messages: $messages,
                systemInstructions: $systemInstructions,
                attachmentReplayMerge: $mergeMode,
                priorAttachmentReplayCount: $priorIncluded,
                priorAttachmentExcludedCount: $priorExcluded,
                priorAttachmentExclusions: $priorExclusions,
                priorReplayAttachments: $replay->files,
                attachmentReplayRequestMode: $modeString,
            );
        }

        return new ProjectedConversationContext(
            context: $context,
            messages: $messages,
            systemInstructions: $systemInstructions,
        );
    }

    /**
     * @param list<File> $userTurnAttachments Files actually sent on this user turn (merged replay + request when applicable).
     */
    public function reconcile(
        ProjectedConversationContext $projected,
        ExecutionRequest $request,
        AgentResponse $response,
        array $userTurnAttachments = [],
    ): ?Conversation {
        $context = $projected->context;

        if (!$context instanceof RunContext) {
            return null;
        }

        $conversation = $context->conversation;

        if (!$conversation instanceof Conversation) {
            return null;
        }

        $timestamp = new DateTimeImmutable();

        $conversation = $conversation
          ->withAppendedMessage($this->createUserMessage($request, $timestamp, $userTurnAttachments), $timestamp)
          ->withAppendedMessage($this->createAssistantMessage($request, $response, $timestamp), $timestamp)
          ->withMetadata(
              metadata: $this->mergeConversationMetadata(
                  $conversation,
                  $request,
                  $response,
                  $projected,
              ),
              updatedAt: $timestamp,
          );

        $persistedContext = $this->conversationContextManager->persist(
            $context->withConversation($conversation),
        );

        return $persistedContext->conversation;
    }

    private function shouldBridge(ExecutionRequest $request): bool
    {
        return $request->storeConversation
          || $request->continueConversation
          || $request->conversationId instanceof ConversationId;
    }

    /**
     * @return array{0: list<Message>, 1: list<string>}
     */
    private function projectConversation(Conversation $conversation): array
    {
        $messages = [];
        $systemInstructions = [];

        foreach ($conversation->messages as $message) {
            if ($message->content === '') {
                continue;
            }

            if ($message->role === ConversationMessageRole::System) {
                $systemInstructions[] = $message->content;
            }

            $messages[] = $this->projectMessage($message);
        }

        return [$messages, $systemInstructions];
    }

    private function projectMessage(ConversationMessage $message): Message
    {
        return match ($message->role) {
            ConversationMessageRole::User => $this->projectUserMessage($message),
            ConversationMessageRole::Assistant => new AssistantMessage($message->content),
            ConversationMessageRole::Tool => new ToolResultMessage(collect([
                $this->toolResultFromConversationMessage($message),
            ])),
            ConversationMessageRole::System => new UserMessage('[system-context] ' . $message->content),
        };
    }

    private function toolResultFromConversationMessage(ConversationMessage $message): ToolResult
    {
        $toolResult = $message->metadata['tool_result'] ?? null;

        if (is_array($toolResult)) {
            return ToolResult::fromArray($toolResult);
        }

        $toolId = $message->metadataValue('tool_id');
        $toolName = $message->metadataValue('tool_name');
        $arguments = $message->metadataValue('tool_arguments');
        $resultId = $message->metadataValue('result_id');

        return new ToolResult(
            id: is_string($toolId) && $toolId !== '' ? $toolId : $message->id->toString(),
            name: is_string($toolName) && $toolName !== '' ? $toolName : 'tool',
            arguments: is_array($arguments) ? $arguments : [],
            result: $message->content,
            resultId: is_string($resultId) ? $resultId : null,
        );
    }

    private function projectUserMessage(ConversationMessage $message): UserMessage
    {
        $attachments = $this->attachmentsFromMessageMetadata($message);

        return new UserMessage($message->content, new Collection($attachments));
    }

    /**
     * @return list<File>
     */
    private function attachmentsFromMessageMetadata(ConversationMessage $message): array
    {
        $raw = $message->metadata['attachments'] ?? null;
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_array($raw)) {
            $rows = array_values($raw);
        } elseif (is_string($raw)) {
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return [];
            }

            $rows = is_array($decoded) ? array_values($decoded) : [];
        } else {
            return [];
        }

        $files = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            /** @var array<string, mixed> $assoc */
            $assoc = [];
            foreach ($row as $key => $value) {
                $assoc[(string) $key] = $value;
            }

            try {
                $files[] = PersistedLaravelAiFileSerializer::fromArray($assoc);
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        return $files;
    }

    /**
     * @param list<File> $userTurnAttachments
     */
    private function createUserMessage(ExecutionRequest $request, DateTimeImmutable $timestamp, array $userTurnAttachments = []): ConversationMessage
    {
        $metadata = [
            'run_id' => $request->runId,
            'provider' => $request->provider,
            'model' => $request->model,
            'tool_names' => $request->toolNames,
        ];

        $toSerialize = $userTurnAttachments !== [] ? $userTurnAttachments : $request->attachments;

        if ($toSerialize !== []) {
            $serialized = [];
            foreach ($toSerialize as $file) {
                $serialized[] = PersistedLaravelAiFileSerializer::toArray($file);
            }

            $metadata['attachments'] = $serialized;
        }

        return new ConversationMessage(
            id: new MessageId((string)Str::uuid()),
            role: ConversationMessageRole::User,
            content: $request->prompt,
            createdAt: $timestamp,
            metadata: $metadata,
        );
    }

    private function createAssistantMessage(
        ExecutionRequest $request,
        AgentResponse $response,
        DateTimeImmutable $timestamp,
    ): ConversationMessage {
        return new ConversationMessage(
            id: new MessageId((string)Str::uuid()),
            role: ConversationMessageRole::Assistant,
            content: $response->text,
            createdAt: $timestamp,
            metadata: [
            'run_id' => $request->runId,
            'provider' => $response->meta->provider,
            'model' => $response->meta->model,
            'invocation_id' => $response->invocationId,
            'sdk_conversation_id' => $response->conversationId,
            'usage' => [
              'prompt_tokens' => $response->usage->promptTokens,
              'completion_tokens' => $response->usage->completionTokens,
              'total_tokens' => $response->usage->promptTokens + $response->usage->completionTokens,
            ],
          ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function mergeConversationMetadata(
        Conversation $conversation,
        ExecutionRequest $request,
        AgentResponse $response,
        ProjectedConversationContext $projected,
    ): array {
        $metadata = $conversation->metadata;
        $metadata['last_run_id'] = $request->runId;
        $metadata['last_provider'] = $response->meta->provider;
        $metadata['last_model'] = $response->meta->model;
        $metadata['last_invocation_id'] = $response->invocationId;
        $metadata['last_sdk_conversation_id'] = $response->conversationId;
        $metadata['last_attachment_replay_merge'] = $projected->attachmentReplayMerge;
        $metadata['last_prior_attachment_replay_count'] = $projected->priorAttachmentReplayCount;
        $metadata['last_prior_attachment_excluded_count'] = $projected->priorAttachmentExcludedCount;

        return $metadata;
    }

    private function attachmentReplayPolicy(): AttachmentReplayPolicy
    {
        $replay = $this->config->get('ai-agent-kit.memory.attachments_replay', []);

        if (!is_array($replay)) {
            return AttachmentReplayPolicy::disabled();
        }

        $denyTypes = $replay['deny_types'] ?? null;
        if (!is_array($denyTypes)) {
            $denyTypes = [
                'base64-image',
                'base64-document',
                'base64-audio',
                'local-image',
                'local-document',
                'local-audio',
            ];
        }

        $denyUrlSubstrings = $replay['deny_url_substrings'] ?? [];
        if (!is_array($denyUrlSubstrings)) {
            $denyUrlSubstrings = [];
        }

        /** @var list<string> $denyTypesList */
        $denyTypesList = array_values(array_filter($denyTypes, static fn (mixed $t): bool => is_string($t) && $t !== ''));
        /** @var list<string> $denyUrlList */
        $denyUrlList = array_values(array_filter($denyUrlSubstrings, static fn (mixed $s): bool => is_string($s) && $s !== ''));

        return new AttachmentReplayPolicy(
            enabled: (bool)($replay['enabled'] ?? false),
            maxPerTurn: isset($replay['max_per_turn']) && is_int($replay['max_per_turn']) ? $replay['max_per_turn'] : null,
            maxAgeSeconds: isset($replay['max_age_seconds']) && is_int($replay['max_age_seconds']) ? $replay['max_age_seconds'] : null,
            denyTypes: $denyTypesList,
            allowProviderReferences: (bool)($replay['allow_provider_references'] ?? false),
            denyUrlSubstrings: $denyUrlList,
        );
    }
}
