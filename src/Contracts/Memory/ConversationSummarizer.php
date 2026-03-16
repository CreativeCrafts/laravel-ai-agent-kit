<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Memory;

use CreativeCrafts\LaravelAiAgentKit\Memory\SummarizationInput;
use CreativeCrafts\LaravelAiAgentKit\Memory\SummarizationResult;

interface ConversationSummarizer
{
    public function shouldSummarize(SummarizationInput $input): bool;

    public function summarize(SummarizationInput $input): SummarizationResult;
}
