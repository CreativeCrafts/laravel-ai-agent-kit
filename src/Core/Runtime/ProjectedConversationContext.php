<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use Laravel\Ai\Files\File;
use Laravel\Ai\Messages\Message;

final readonly class ProjectedConversationContext
{
    /**
     * @param list<Message> $messages
     * @param list<string> $systemInstructions
     * @param list<File> $priorReplayAttachments
     * @param list<array{type: ?string, reason: string}> $priorAttachmentExclusions
     */
    public function __construct(
        public ?RunContext $context,
        public array $messages = [],
        public array $systemInstructions = [],
        public string $attachmentReplayMerge = 'none',
        public int $priorAttachmentReplayCount = 0,
        public int $priorAttachmentExcludedCount = 0,
        public array $priorAttachmentExclusions = [],
        public array $priorReplayAttachments = [],
        public ?string $attachmentReplayRequestMode = null,
    ) {
    }

    public function projectedMessageCount(): int
    {
        return count($this->messages);
    }
}
