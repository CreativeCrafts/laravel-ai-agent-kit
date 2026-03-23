<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Pipeline;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineRunner;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationContextManager;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\Redactor;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Exceptions\PipelineExecutionException;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\PipelineCompleted;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\PipelineStarted;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\PipelineStepCompleted;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\PipelineStepFailed;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\PipelineStepStarted;
use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

final readonly class SynchronousPipelineRunner implements PipelineRunner
{
    public function __construct(
        private ?ConversationContextManager $conversationContextManager = null,
        private ?Dispatcher $events = null,
        private ?Redactor $redactor = null,
    ) {
    }

    public function run(Pipeline $pipeline, RunContext $context): RunContext
    {
        $currentContext = $this->initializeConversationContext($context);
        $steps = $pipeline->steps();

        $this->dispatch(PipelineStarted::fromContext($currentContext, count($steps), $this->redactor));

        foreach ($steps as $index => $step) {
            $stepIndex = $index + 1;
            $stepClass = $step::class;

            $this->dispatch(PipelineStepStarted::fromContext($currentContext, $stepClass, $stepIndex, $this->redactor));

            try {
                $currentContext = $step->handle($currentContext);
            } catch (Throwable $throwable) {
                $this->dispatch(PipelineStepFailed::fromContext($currentContext, $stepClass, $stepIndex, $throwable));

                throw PipelineExecutionException::forStep($stepClass, $throwable);
            }

            $this->dispatch(PipelineStepCompleted::fromContext($currentContext, $stepClass, $stepIndex, $this->redactor));
        }

        $persistedContext = $this->persistConversationContext($currentContext);

        $this->dispatch(PipelineCompleted::fromContext($persistedContext, $this->redactor));

        return $persistedContext;
    }

    private function initializeConversationContext(RunContext $context): RunContext
    {
        if (!$this->conversationContextManager instanceof ConversationContextManager) {
            return $context;
        }

        return $this->conversationContextManager->initialize($context);
    }

    private function dispatch(object $event): void
    {
        if ($this->events instanceof Dispatcher) {
            $this->events->dispatch($event);
        }
    }

    private function persistConversationContext(RunContext $context): RunContext
    {
        if (!$this->conversationContextManager instanceof ConversationContextManager) {
            return $context;
        }

        return $this->conversationContextManager->persist($context);
    }
}
