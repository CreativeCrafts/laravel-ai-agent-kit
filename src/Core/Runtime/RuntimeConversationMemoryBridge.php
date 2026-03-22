<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationContextManager;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\ConversationContextBridgeException;
use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessage;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessageRole;
use CreativeCrafts\LaravelAiAgentKit\Memory\MessageId;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\AgentResponse;

final readonly class RuntimeConversationMemoryBridge
{
    public function __construct(
        private ConversationContextManager $conversationContextManager,
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

        return new ProjectedConversationContext(
            context: $context,
            messages: $messages,
            systemInstructions: $systemInstructions,
        );
    }

    public function reconcile(
        ProjectedConversationContext $projected,
        ExecutionRequest $request,
        AgentResponse $response,
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
          ->withAppendedMessage($this->createUserMessage($request, $timestamp), $timestamp)
          ->withAppendedMessage($this->createAssistantMessage($request, $response, $timestamp), $timestamp)
          ->withMetadata(
              metadata: $this->mergeConversationMetadata($conversation, $request, $response),
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

                continue;
            }

            $projected = $this->projectMessage($message);

            if ($projected instanceof Message) {
                $messages[] = $projected;
            }
        }

        return [$messages, $systemInstructions];
    }

    private function projectMessage(ConversationMessage $message): ?Message
    {
        return match ($message->role) {
            ConversationMessageRole::User => new UserMessage($message->content),
            ConversationMessageRole::Assistant => new AssistantMessage($message->content),
            ConversationMessageRole::Tool, ConversationMessageRole::System => null,
        };
    }

    private function createUserMessage(ExecutionRequest $request, DateTimeImmutable $timestamp): ConversationMessage
    {
        return new ConversationMessage(
            id: new MessageId((string)Str::uuid()),
            role: ConversationMessageRole::User,
            content: $request->prompt,
            createdAt: $timestamp,
            metadata: [
            'run_id' => $request->runId,
            'provider' => $request->provider,
            'model' => $request->model,
            'tool_names' => $request->toolNames,
          ],
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
    ): array {
        $metadata = $conversation->metadata;
        $metadata['last_run_id'] = $request->runId;
        $metadata['last_provider'] = $response->meta->provider;
        $metadata['last_model'] = $response->meta->model;
        $metadata['last_invocation_id'] = $response->invocationId;
        $metadata['last_sdk_conversation_id'] = $response->conversationId;

        return $metadata;
    }
}
