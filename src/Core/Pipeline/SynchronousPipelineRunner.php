<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Pipeline;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineRunner;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationContextManager;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Exceptions\PipelineExecutionException;
use Throwable;

final readonly class SynchronousPipelineRunner implements PipelineRunner
{
    public function __construct(
        private ?ConversationContextManager $conversationContextManager = null,
    ) {
    }

    public function run(Pipeline $pipeline, RunContext $context): RunContext
    {
        $currentContext = $this->initializeConversationContext($context);

        foreach ($pipeline->steps() as $step) {
            try {
                $currentContext = $step->handle($currentContext);
            } catch (Throwable $throwable) {
                throw PipelineExecutionException::forStep($step::class, $throwable);
            }
        }

        return $this->persistConversationContext($currentContext);
    }

    private function initializeConversationContext(RunContext $context): RunContext
    {
        if (!$this->conversationContextManager instanceof ConversationContextManager) {
            return $context;
        }

        return $this->conversationContextManager->initialize($context);
    }

    private function persistConversationContext(RunContext $context): RunContext
    {
        if (!$this->conversationContextManager instanceof ConversationContextManager) {
            return $context;
        }

        return $this->conversationContextManager->persist($context);
    }
}
