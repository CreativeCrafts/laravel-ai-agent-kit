<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Pipeline;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineRunner;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineStep;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationContextManager;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience\RetryPolicyResolver;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\Redactor;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Exceptions\PipelineExecutionException;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\PipelineCompleted;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\PipelineStarted;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\PipelineStepCompleted;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\PipelineStepFailed;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\PipelineStepStarted;
use CreativeCrafts\LaravelAiAgentKit\Resilience\BackoffStrategyConfig;
use CreativeCrafts\LaravelAiAgentKit\Resilience\enums\BackoffStrategy;
use CreativeCrafts\LaravelAiAgentKit\Resilience\PipelineBudgetEnforcer;
use CreativeCrafts\LaravelAiAgentKit\Resilience\RetryPolicy;
use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

final readonly class SynchronousPipelineRunner implements PipelineRunner
{
    public function __construct(
        private ?ConversationContextManager $conversationContextManager = null,
        private ?Dispatcher $events = null,
        private ?Redactor $redactor = null,
        private ?PipelineBudgetEnforcer $budgetEnforcer = null,
        private ?RetryPolicyResolver $retryPolicyResolver = null,
    ) {
    }

    public function run(Pipeline $pipeline, RunContext $context): RunContext
    {
        $startedAt = microtime(true);
        $currentContext = $this->initializeConversationContext($context);
        $steps = $pipeline->steps();

        $this->dispatch(PipelineStarted::fromContext($currentContext, count($steps), $this->redactor));

        foreach ($steps as $index => $step) {
            $stepIndex = $index + 1;
            $stepClass = $step::class;

            $this->enforceStepBudget($currentContext, $startedAt);

            $this->dispatch(PipelineStepStarted::fromContext($currentContext, $stepClass, $stepIndex, $this->redactor));

            try {
                $currentContext = $this->executeStepWithRetry(
                    step: $step,
                    currentContext: $currentContext,
                    startedAt: $startedAt,
                );
            } catch (Throwable $throwable) {
                $this->dispatch(PipelineStepFailed::fromContext($currentContext, $stepClass, $stepIndex, $throwable));

                if ($throwable instanceof PipelineExecutionException) {
                    throw $throwable;
                }

                throw PipelineExecutionException::forStep($stepClass, $throwable);
            }

            $this->enforceTimeoutBudget($startedAt);
            $this->dispatch(PipelineStepCompleted::fromContext($currentContext, $stepClass, $stepIndex, $this->redactor));
        }

        $this->enforceTimeoutBudget($startedAt);

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

    private function enforceStepBudget(RunContext $context, float $startedAt): void
    {
        if (!$this->budgetEnforcer instanceof PipelineBudgetEnforcer) {
            return;
        }

        $this->budgetEnforcer->assertCanExecuteStep($context->stepCount + 1, $startedAt);
    }

    private function executeStepWithRetry(PipelineStep $step, RunContext $currentContext, float $startedAt): RunContext
    {
        $policy = $this->resolveRetryPolicy();
        $attempt = 1;

        while (true) {
            try {
                return $step->handle($currentContext);
            } catch (Throwable $throwable) {
                if (!$policy->allowsRetryAfterAttempt($attempt)) {
                    throw $throwable;
                }

                $delayForRetry = $policy->delayForRetry($attempt);

                if ($delayForRetry > 0) {
                    usleep($delayForRetry * 1000);
                }

                $attempt++;
                $this->enforceTimeoutBudget($startedAt);
            }
        }
    }

    private function resolveRetryPolicy(): RetryPolicy
    {
        if ($this->retryPolicyResolver instanceof RetryPolicyResolver) {
            return $this->retryPolicyResolver->resolve();
        }

        return new RetryPolicy(
            enabled: false,
            maxAttempts: 1,
            backoff: new BackoffStrategyConfig(
                strategy: BackoffStrategy::Constant,
                baseDelayMs: 0,
                maxDelayMs: 0,
                multiplier: 1.0,
            ),
        );
    }

    private function enforceTimeoutBudget(float $startedAt): void
    {
        if ($this->budgetEnforcer instanceof PipelineBudgetEnforcer) {
            $this->budgetEnforcer->assertWithinTimeout($startedAt);
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
