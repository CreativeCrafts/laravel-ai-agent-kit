<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Memory;

use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;

interface ConversationContextManager
{
    public function start(
        RunContext $context,
        ?ConversationId $conversationId = null,
        bool $storeConversation = true,
    ): RunContext;

    public function continue(
        RunContext $context,
        ConversationId $conversationId,
        bool $storeConversation = true,
    ): RunContext;

    public function initialize(RunContext $context): RunContext;

    public function persist(RunContext $context): RunContext;
}
