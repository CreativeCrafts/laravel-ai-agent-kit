<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use Laravel\Ai\Messages\Message;

final readonly class ProjectedConversationContext
{
    /**
     * @param list<Message> $messages
     * @param list<string> $systemInstructions
     */
    public function __construct(
        public ?RunContext $context,
        public array $messages = [],
        public array $systemInstructions = [],
    ) {
    }

    public function projectedMessageCount(): int
    {
        return count($this->messages);
    }
}
