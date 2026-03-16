<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Memory;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationSummarizer;

final readonly class NullConversationSummarizer implements ConversationSummarizer
{
    public function __construct(
        private bool $enabled = false,
        private int $triggerMessageCount = 20,
    ) {
    }

    public function shouldSummarize(SummarizationInput $input): bool
    {
        return $this->enabled && $input->messageCount() >= $this->triggerMessageCount;
    }

    public function summarize(SummarizationInput $input): SummarizationResult
    {
        return new SummarizationResult(
            summary: $input->existingSummary,
            shouldPersist: false,
            summarizedMessageCount: $input->messageCount(),
            metadata: [
            'driver' => 'null',
            'trigger_message_count' => $this->triggerMessageCount,
          ],
        );
    }
}
