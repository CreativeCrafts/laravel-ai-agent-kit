<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Exceptions\TextToStructuredEvaluationException;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\AgentRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationContextManager;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\AgentProviderProfileSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\Redactor;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\SynchronousAgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredAgentProviderProfileSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredFailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\RuntimeBudgetExceededException;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions\RuntimeExecutionException;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\RuntimeConversationMemoryBridge;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\SdkAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Observability\Contracts\HasFailureCategory;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\OrchestrationFailed;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\ProviderFailoverExhausted;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\ProviderFailoverResolved;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\RuntimeExecutionFailed;
use CreativeCrafts\LaravelAiAgentKit\Observability\Support\FailureCategory;
use CreativeCrafts\LaravelAiAgentKit\Observability\Support\FailureCategoryResolver;
use CreativeCrafts\LaravelAiAgentKit\Resilience\RuntimeBudgetEnforcer;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents\OrchestratorRefusalAgent;
use CreativeCrafts\LaravelAiAgentKit\Tools\SdkToolMaterializer;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Event;

it('resolves categories through the package-owned failure-category carrier interface', function (): void {
    $throwable = new class ('carrier failure') extends RuntimeException implements HasFailureCategory {
        public function failureCategory(): string
        {
            return FailureCategory::ProviderFailure->value;
        }
    };

    expect(FailureCategoryResolver::forThrowable($throwable))
      ->toBe(FailureCategory::ProviderFailure->value);
});

it('exposes stable package-owned failure categories for structured output and budget failures', function (): void {
    expect(TextToStructuredEvaluationException::invalidSpecialistPayload('summary must be present.')->failureCategory())
      ->toBe(FailureCategory::InvalidOutput->value)
      ->and(TextToStructuredEvaluationException::invalidJson('not-json')->failureCategory())
      ->toBe(FailureCategory::MalformedOutput->value)
      ->and(TextToStructuredEvaluationException::refusedStructuredOutput('I cannot comply.')->failureCategory())
      ->toBe(FailureCategory::Refusal->value)
      ->and(RuntimeBudgetExceededException::forInvalidEstimatedCostType('run-budget-001', 'string')->failureCategory())
      ->toBe(FailureCategory::BudgetExceeded->value);
});

it('emits redacted runtime failure telemetry when execution fails before provider completion', function (): void {
    Event::fake([
      RuntimeExecutionFailed::class,
    ]);

    $runtime = runtimeForFailureTests(throwingConversationContextManager());

    try {
        $runtime->execute(
            new ExecutionRequest(
                runId: 'run-runtime-failure-001',
                prompt: 'Handle api_token protected work.',
                provider: 'openai',
                model: 'gpt-4o-mini',
                input: ['api_token' => 'secret'],
                metadata: ['trace_id' => 'trace-001'],
                storeConversation: true,
            ),
        );

        $this->fail('Expected runtime execution failure was not thrown.');
    } catch (RuntimeExecutionException) {
        // expected
    }

    Event::assertDispatched(RuntimeExecutionFailed::class, function (RuntimeExecutionFailed $event): bool {
        expect($event->runId)
          ->toBe('run-runtime-failure-001')
          ->and($event->provider)->toBe('openai')
          ->and($event->model)->toBe('gpt-4o-mini')
          ->and($event->requestedToolNames)->toBe([])
          ->and($event->inputKeys)->toBe(['[redacted-key]'])
          ->and($event->metadataKeys)->toBe(['trace_id'])
          ->and($event->storeConversation)->toBeTrue()
          ->and($event->continueConversation)->toBeFalse()
          ->and($event->projectedMessageCount)->toBe(0)
          ->and($event->failureCategory)->toBe(FailureCategory::ExecutionFailed->value)
          ->and($event->exceptionClass)->toBe(RuntimeExecutionException::class)
          ->and($event->exceptionMessage)->toBe(
              app(Redactor::class)->redactText('AI runtime execution failed for run [run-runtime-failure-001]'),
          )
          ->and(property_exists($event, 'prompt'))->toBeFalse();

        return true;
    });
});

it('emits provider-failure runtime telemetry when the provider prompt edge fails', function (): void {
    Event::fake([
      RuntimeExecutionFailed::class,
    ]);

    $runtime = runtimeForFailureTests(noOpConversationContextManager());

    try {
        $runtime->execute(
            new ExecutionRequest(
                runId: 'run-provider-failure-001',
                prompt: 'Handle api_token protected work.',
                provider: 'missing-provider',
                model: 'gpt-4o-mini',
                input: ['api_token' => 'secret'],
                metadata: ['trace_id' => 'trace-provider-001'],
            ),
        );

        $this->fail('Expected provider failure was not thrown.');
    } catch (RuntimeExecutionException $exception) {
        expect($exception->failureCategory())
          ->toBe(FailureCategory::ProviderFailure->value);
    }

    Event::assertDispatched(RuntimeExecutionFailed::class, function (RuntimeExecutionFailed $event): bool {
        expect($event->runId)
          ->toBe('run-provider-failure-001')
          ->and($event->provider)->toBe('missing-provider')
          ->and($event->model)->toBe('gpt-4o-mini')
          ->and($event->requestedToolNames)->toBe([])
          ->and($event->inputKeys)->toBe(['[redacted-key]'])
          ->and($event->metadataKeys)->toBe(['trace_id'])
          ->and($event->projectedMessageCount)->toBe(0)
          ->and($event->failureCategory)->toBe(FailureCategory::ProviderFailure->value)
          ->and($event->exceptionClass)->toBe(RuntimeExecutionException::class)
          ->and($event->exceptionMessage)->toBe(
              app(Redactor::class)->redactText('AI runtime execution failed for run [run-provider-failure-001]'),
          )
          ->and(property_exists($event, 'prompt'))->toBeFalse();

        return true;
    });
});

it('emits budget-exceeded runtime failure telemetry without leaking raw request content', function (): void {
    Event::fake([
      RuntimeExecutionFailed::class,
    ]);

    $runtime = runtimeForFailureTests(noOpConversationContextManager());

    try {
        $runtime->execute(
            new ExecutionRequest(
                runId: 'run-budget-failure-001',
                prompt: 'Estimate api_token protected work.',
                provider: 'openai',
                model: 'gpt-4o-mini',
                metadata: [
              'estimated_cost_usd' => 'not-a-number',
              'safe_key' => 'visible',
            ],
            ),
        );

        $this->fail('Expected runtime budget failure was not thrown.');
    } catch (RuntimeBudgetExceededException) {
        // expected
    }

    Event::assertDispatched(RuntimeExecutionFailed::class, function (RuntimeExecutionFailed $event): bool {
        expect($event->runId)
          ->toBe('run-budget-failure-001')
          ->and($event->failureCategory)->toBe(FailureCategory::BudgetExceeded->value)
          ->and($event->exceptionClass)->toBe(RuntimeBudgetExceededException::class)
          ->and($event->metadataKeys)->toContain('safe_key')
          ->and(property_exists($event, 'prompt'))->toBeFalse();

        return true;
    });
});

it('emits explicit failover exhaustion telemetry when no later provider remains eligible', function (): void {
    Event::fake([
      ProviderFailoverExhausted::class,
      ProviderFailoverResolved::class,
    ]);

    $config = new Repository([
      'ai-agent-kit' => [
        'failover_order' => ['openai-primary', 'anthropic-secondary'],
        'resilience' => [
          'circuit_breaker' => [
            'apply_to_failover' => false,
          ],
        ],
        'providers' => [
          'openai-primary' => [
            'driver' => 'openai',
            'enabled' => true,
            'capabilities' => ['text_generation'],
            'options' => [],
          ],
          'anthropic-secondary' => [
            'driver' => 'anthropic',
            'enabled' => true,
            'capabilities' => ['text_generation'],
            'options' => [],
          ],
        ],
      ],
    ]);

    $selector = new ConfiguredFailoverProviderSelector(
        config: $config,
        providerRegistry: new ConfiguredProviderRegistry($config),
        events: Event::getFacadeRoot(),
    );

    expect($selector->nextAfter('anthropic-secondary'))->toBeNull();

    Event::assertDispatched(ProviderFailoverExhausted::class, function (ProviderFailoverExhausted $event): bool {
        return $event->currentProvider === 'anthropic-secondary'
          && $event->orderedProviders === ['openai-primary', 'anthropic-secondary'];
    });

    Event::assertDispatched(ProviderFailoverResolved::class, function (ProviderFailoverResolved $event): bool {
        return $event->currentProvider === 'anthropic-secondary'
          && $event->nextProvider === null
          && $event->orderedProviders === ['openai-primary', 'anthropic-secondary'];
    });
});

it('emits a refusal-category orchestration failure event for package-owned refusal exceptions', function (): void {
    config()->set('ai-agent-kit.providers', [
      'openai-refusal' => [
        'driver' => 'openai',
        'enabled' => true,
        'capabilities' => ['text_generation'],
        'options' => [],
      ],
    ]);

    app()->forgetInstance(ConfiguredProviderRegistry::class);
    app()->forgetInstance(ProviderRegistry::class);
    app()->forgetInstance(ConfiguredAgentProviderProfileSelector::class);
    app()->forgetInstance(AgentProviderProfileSelector::class);
    app()->forgetInstance(SynchronousAgentOrchestrator::class);
    app()->forgetInstance(AgentOrchestrator::class);

    $registry = app(AgentRegistry::class);

    if (!$registry->has('refusal.agent')) {
        $registry->register(OrchestratorRefusalAgent::class);
    }

    Event::fake([
      OrchestrationFailed::class,
    ]);

    try {
        app(AgentOrchestrator::class)->run(
            new OrchestrationRequest(
                entryAgent: 'refusal.agent',
                task: 'Trigger api_token refusal',
            ),
        );

        $this->fail('Expected refusal exception was not thrown.');
    } catch (TextToStructuredEvaluationException) {
        // expected
    }

    Event::assertDispatched(OrchestrationFailed::class, function (OrchestrationFailed $event): bool {
        expect($event->entryAgent)
          ->toBe('refusal.agent')
          ->and($event->failureCategory)->toBe(FailureCategory::Refusal->value)
          ->and($event->exceptionClass)->toBe(TextToStructuredEvaluationException::class)
          ->and($event->task)->toBe(app(Redactor::class)->redactText('Trigger api_token refusal'))
          ->and($event->exceptionMessage)->toBeString()
          ->and($event->exceptionMessage)->not->toContain('I cannot comply with the api_token protected request.');

        return true;
    });
});

function runtimeForFailureTests(ConversationContextManager $conversationContextManager): SdkAiRuntime
{
    /** @var Dispatcher $eventDispatcher */
    $eventDispatcher = Event::getFacadeRoot();

    return new SdkAiRuntime(
        toolMaterializer: app(SdkToolMaterializer::class),
        runtimeConversationMemoryBridge: new RuntimeConversationMemoryBridge($conversationContextManager),
        runtimeBudgetEnforcer: new RuntimeBudgetEnforcer(
            new Repository([
          'ai-agent-kit' => [
            'budgets' => [],
          ],
        ]),
        ),
        events: $eventDispatcher,
        redactor: app(Redactor::class),
    );
}

function noOpConversationContextManager(): ConversationContextManager
{
    return new class () implements ConversationContextManager {
        public function start(RunContext $context, ?ConversationId $conversationId = null, bool $storeConversation = true): RunContext
        {
            return $context;
        }

        public function continue(RunContext $context, ConversationId $conversationId, bool $storeConversation = true): RunContext
        {
            return $context;
        }

        public function initialize(RunContext $context): RunContext
        {
            return $context;
        }

        public function persist(RunContext $context): RunContext
        {
            return $context;
        }
    };
}

function throwingConversationContextManager(): ConversationContextManager
{
    return new class () implements ConversationContextManager {
        public function start(RunContext $context, ?ConversationId $conversationId = null, bool $storeConversation = true): RunContext
        {
            throw new RuntimeException('Bridge api_token failure');
        }

        public function continue(RunContext $context, ConversationId $conversationId, bool $storeConversation = true): RunContext
        {
            throw new RuntimeException('Bridge api_token failure');
        }

        public function initialize(RunContext $context): RunContext
        {
            return $context;
        }

        public function persist(RunContext $context): RunContext
        {
            return $context;
        }
    };
}
