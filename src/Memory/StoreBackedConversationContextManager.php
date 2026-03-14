<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Memory;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationContextManager;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use CreativeCrafts\LaravelAiAgentKit\Memory\Exceptions\ConversationNotFoundException;
use DateTimeImmutable;
use Illuminate\Support\Str;

final readonly class StoreBackedConversationContextManager implements ConversationContextManager
{
    public function __construct(
        private ConversationStore $conversationStore,
    ) {
    }

    public function initialize(RunContext $context): RunContext
    {
        if (!$context->hasConversationId()) {
            return $context;
        }

        if ($context->hasConversation()) {
            return $context;
        }

        $conversationId = $context->conversationId;

        if (!$conversationId instanceof ConversationId) {
            return $context;
        }

        if ($context->shouldContinueConversation()) {
            return $this->continue(
                context: $context,
                conversationId: $conversationId,
                storeConversation: $context->shouldStoreConversation(),
            );
        }

        return $this->start(
            context: $context,
            conversationId: $conversationId,
            storeConversation: $context->shouldStoreConversation(),
        );
    }

    public function continue(
        RunContext $context,
        ConversationId $conversationId,
        bool $storeConversation = true,
    ): RunContext {
        $conversation = $this->conversationStore->find($conversationId);

        if (!$conversation instanceof Conversation) {
            throw ConversationNotFoundException::forId($conversationId);
        }

        return $context
          ->forExistingConversation($conversationId, $storeConversation)
          ->withConversation($conversation);
    }

    public function start(
        RunContext $context,
        ?ConversationId $conversationId = null,
        bool $storeConversation = true,
    ): RunContext {
        $resolvedConversationId = $conversationId ?? new ConversationId((string)Str::uuid());
        $timestamp = new DateTimeImmutable();

        $conversation = new Conversation(
            id: $resolvedConversationId,
            createdAt: $timestamp,
            updatedAt: $timestamp,
        );

        return $context
          ->forNewConversation($resolvedConversationId, $storeConversation)
          ->withConversation($conversation);
    }

    public function persist(RunContext $context): RunContext
    {
        if (!$context->shouldStoreConversation()) {
            return $context;
        }

        $conversation = $context->conversation;

        if (!$conversation instanceof Conversation) {
            return $context;
        }

        $this->conversationStore->save($conversation);

        return $context;
    }
}
