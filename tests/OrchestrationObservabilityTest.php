<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\AgentRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\AgentProviderProfileSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\Redactor;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\Exceptions\InvalidDelegationTargetException;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\ExecutionTraceRecord;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\SynchronousAgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredAgentProviderProfileSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\OrchestrationCompleted;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\OrchestrationDelegated;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\OrchestrationFailed;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\OrchestrationStarted;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents\OrchestratorFailingAgent;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents\OrchestratorGreetingAgent;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents\OrchestratorInvalidDelegationAgent;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents\OrchestratorListOutputAgent;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents\OrchestratorRefundAgent;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents\OrchestratorSupportAgent;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    config()->set('ai-agent-kit.providers', [
      'openai-greeting' => [
        'driver' => 'openai',
        'enabled' => true,
        'capabilities' => ['text_generation'],
        'options' => [],
      ],
      'anthropic-support' => [
        'driver' => 'anthropic',
        'enabled' => true,
        'capabilities' => ['text_generation'],
        'options' => [],
      ],
      'openai-support' => [
        'driver' => 'openai',
        'enabled' => true,
        'capabilities' => ['text_generation'],
        'options' => [],
      ],
      'openai-refund' => [
        'driver' => 'openai',
        'enabled' => true,
        'capabilities' => ['structured_output'],
        'options' => [],
      ],
      'openai-invalid-delegation' => [
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
    $registry->registerMany([
      OrchestratorGreetingAgent::class,
      OrchestratorFailingAgent::class,
      OrchestratorRefundAgent::class,
      OrchestratorSupportAgent::class,
      OrchestratorInvalidDelegationAgent::class,
      OrchestratorListOutputAgent::class,
    ]);
});

function redact(string $value): string
{
    return app(Redactor::class)->redactText($value);
}

it('emits orchestration lifecycle events with redacted metadata and trace keys', function () {
    Event::fake([
      OrchestrationStarted::class,
      OrchestrationDelegated::class,
      OrchestrationCompleted::class,
    ]);

    $result = app(AgentOrchestrator::class)->run(
        new OrchestrationRequest(
            entryAgent: 'support.agent',
            task: 'Handle the support workflow',
            input: ['subscription_id' => 'sub-123'],
            metadata: [
          'customer_email' => 'customer@example.com',
          'api_token' => 'secret-token',
          'safe_key' => 'visible-key',
        ],
            conversationId: new ConversationId('conv-orch-events-001'),
        ),
    );

    expect($result->completed())->toBeTrue();

    Event::assertDispatched(OrchestrationStarted::class, function (OrchestrationStarted $event): bool {
        return $event->entryAgent === 'support.agent'
          && $event->conversationId === 'conv-orch-events-001'
          && $event->task === redact('Handle the support workflow')
          && in_array('[redacted-key]', $event->metadataKeys, true)
          && in_array('safe_key', $event->metadataKeys, true);
    });

    Event::assertDispatched(OrchestrationDelegated::class, function (OrchestrationDelegated $event): bool {
        return $event->agentKey === 'support.agent'
          && $event->targetAgent === 'refund.agent'
          && $event->delegationMode === 'delegate_and_resume'
          && $event->policyMode === 'static_only'
          && in_array('task', $event->traceMetadataKeys, true)
          && in_array('delegation_mode', $event->traceMetadataKeys, true)
          && in_array('policy_mode', $event->traceMetadataKeys, true);
    });

    Event::assertDispatched(OrchestrationCompleted::class, function (OrchestrationCompleted $event): bool {
        return $event->finalAgent === 'support.agent'
          && $event->traceCount === 3
          && $event->summary === redact('Support agent resumed after specialist delegation.')
          && in_array('workflow', $event->finalOutputKeys, true)
          && isset($event->trace[0]['metadata_keys'])
          && in_array('task', $event->trace[0]['metadata_keys'], true);
    });
});

it('emits a failure event when orchestration ends with a terminal fail result', function () {
    Event::fake([
      OrchestrationCompleted::class,
      OrchestrationFailed::class,
    ]);

    $result = app(AgentOrchestrator::class)->run(
        new OrchestrationRequest(
            entryAgent: 'failing.agent',
            task: 'Handle api_token protected work',
            input: ['requester' => 'customer@example.com'],
            conversationId: new ConversationId('conv-orch-fail-result-001'),
        ),
    );

    expect($result->failed())->toBeTrue();

    Event::assertDispatched(OrchestrationFailed::class, function (OrchestrationFailed $event): bool {
        return $event->entryAgent === 'failing.agent'
          && $event->conversationId === 'conv-orch-fail-result-001'
          && $event->exceptionClass === null
          && $event->exceptionMessage === null
          && $event->status === OrchestrationResult::STATUS_FAILED
          && $event->failureReason === redact('Failing agent could not process api_token protected work.');
    });

    Event::assertDispatched(OrchestrationCompleted::class, function (OrchestrationCompleted $event): bool {
        return $event->status === OrchestrationResult::STATUS_FAILED
          && $event->summary === redact('Failing agent could not process api_token protected work.');
    });
});

it('emits a failure event when orchestration terminates with an exception', function () {
    Event::fake([
      OrchestrationStarted::class,
      OrchestrationFailed::class,
    ]);

    try {
        app(AgentOrchestrator::class)->run(
            new OrchestrationRequest(
                entryAgent: 'invalid-delegation.agent',
                task: 'Trigger an invalid delegation',
                conversationId: new ConversationId('conv-orch-fail-001'),
            ),
        );

        $this->fail('Expected invalid delegation failure was not thrown.');
    } catch (InvalidDelegationTargetException) {
        // expected
    }

    Event::assertDispatched(OrchestrationStarted::class);
    Event::assertDispatched(OrchestrationFailed::class, function (OrchestrationFailed $event): bool {
        return $event->entryAgent === 'invalid-delegation.agent'
          && $event->conversationId === 'conv-orch-fail-001'
          && $event->exceptionClass === InvalidDelegationTargetException::class
          && $event->exceptionMessage !== '';
    });
});

it('works correctly when no event dispatcher is configured', function () {
    app()->forgetInstance(SynchronousAgentOrchestrator::class);
    app()->forgetInstance(AgentOrchestrator::class);

    app()->singleton(AgentOrchestrator::class, function ($app) {
        return new SynchronousAgentOrchestrator(
            agentRegistry: $app->make(AgentRegistry::class),
            events: null,
            redactor: null,
        );
    });

    $result = app(AgentOrchestrator::class)->run(
        new OrchestrationRequest(
            entryAgent: 'support.agent',
            task: 'Run without dispatcher',
            input: ['key' => 'value'],
        ),
    );

    expect($result->completed())->toBeTrue();
});

it('ignores started event listener exceptions and still completes the orchestration', function () {
    $startedCalls = 0;

    app()->forgetInstance(SynchronousAgentOrchestrator::class);
    app()->forgetInstance(AgentOrchestrator::class);

    /** @var Dispatcher $realDispatcher */
    $realDispatcher = app(Dispatcher::class);
    $realDispatcher->listen(OrchestrationStarted::class, function () use (&$startedCalls) {
        $startedCalls++;

        throw new RuntimeException('Started listener blew up');
    });

    $result = app(AgentOrchestrator::class)->run(
        new OrchestrationRequest(
            entryAgent: 'support.agent',
            task: 'Run with broken started listener',
            input: ['key' => 'value'],
        ),
    );

    expect($startedCalls)
      ->toBe(1)
      ->and($result->completed())->toBeTrue();
});

it('ignores delegated event listener exceptions and still completes the orchestration', function () {
    $delegatedCalls = 0;

    app()->forgetInstance(SynchronousAgentOrchestrator::class);
    app()->forgetInstance(AgentOrchestrator::class);

    /** @var Dispatcher $realDispatcher */
    $realDispatcher = app(Dispatcher::class);
    $realDispatcher->listen(OrchestrationDelegated::class, function () use (&$delegatedCalls) {
        $delegatedCalls++;

        throw new RuntimeException('Delegated listener blew up');
    });

    $result = app(AgentOrchestrator::class)->run(
        new OrchestrationRequest(
            entryAgent: 'support.agent',
            task: 'Run with broken delegated listener',
            input: ['subscription_id' => 'sub-123'],
        ),
    );

    expect($delegatedCalls)
      ->toBe(1)
      ->and($result->completed())->toBeTrue();
});

it('does not emit a false failure event when the completed event listener throws', function () {
    $dispatched = [];
    $completedCalls = 0;

    app()->forgetInstance(SynchronousAgentOrchestrator::class);
    app()->forgetInstance(AgentOrchestrator::class);

    /** @var Dispatcher $realDispatcher */
    $realDispatcher = app(Dispatcher::class);
    $realDispatcher->listen(OrchestrationCompleted::class, function () use (&$completedCalls) {
        $completedCalls++;

        throw new RuntimeException('Listener blew up');
    });
    $realDispatcher->listen(OrchestrationFailed::class, function (OrchestrationFailed $event) use (&$dispatched) {
        $dispatched[] = $event;
    });

    $result = app(AgentOrchestrator::class)->run(
        new OrchestrationRequest(
            entryAgent: 'support.agent',
            task: 'Run with broken listener',
            input: ['key' => 'value'],
        ),
    );

    expect($completedCalls)
      ->toBe(1)
      ->and($result->completed())->toBeTrue()
      ->and($dispatched)->toBeEmpty();
});

it('completes orchestrations with list-shaped request data without crashing observability', function () {
    Event::fake([
      OrchestrationStarted::class,
      OrchestrationCompleted::class,
    ]);

    $result = app(AgentOrchestrator::class)->run(
        new OrchestrationRequest(
            entryAgent: 'greeting.agent',
            task: 'Say hello with list payload',
            input: ['Ada'],
            metadata: ['trace-a'],
        ),
    );

    expect($result->completed())->toBeTrue();

    Event::assertDispatched(OrchestrationStarted::class, function (OrchestrationStarted $event): bool {
        return $event->inputKeys === []
          && $event->metadataKeys === [];
    });
});

it('completes orchestrations with list-shaped final output without crashing observability', function () {
    Event::fake([
      OrchestrationCompleted::class,
    ]);

    $result = app(AgentOrchestrator::class)->run(
        new OrchestrationRequest(
            entryAgent: 'list-output.agent',
            task: 'Return list output',
            input: ['Ada'],
        ),
    );

    expect($result->completed())
      ->toBeTrue()
      ->and($result->finalOutput)
      ->toBe(['item:Ada', 'item:beta']);

    Event::assertDispatched(OrchestrationCompleted::class, function (OrchestrationCompleted $event): bool {
        return $event->finalOutputKeys === [];
    });
});

it('constructs OrchestrationStarted from request correctly', function () {
    $request = new OrchestrationRequest(
        entryAgent: 'test.agent',
        task: 'Test task',
        input: ['foo' => 'bar', 'baz' => 'qux'],
        metadata: ['key1' => 'val1'],
        conversationId: new ConversationId('conv-123'),
    );

    $event = OrchestrationStarted::fromRequest('orch-id-1', $request);

    expect($event->orchestrationId)
      ->toBe('orch-id-1')
      ->and($event->entryAgent)->toBe('test.agent')
      ->and($event->task)->toBe('Test task')
      ->and($event->inputKeys)->toBe(['foo', 'baz'])
      ->and($event->metadataKeys)->toBe(['key1'])
      ->and($event->conversationId)->toBe('conv-123');
});

it('constructs OrchestrationStarted with redactor applied', function () {
    $redactor = app(Redactor::class);

    $request = new OrchestrationRequest(
        entryAgent: 'test.agent',
        task: 'Test task',
        input: ['foo' => 'bar'],
        metadata: ['api_token' => 'secret', 'safe_key' => 'visible'],
    );

    $event = OrchestrationStarted::fromRequest('orch-id-2', $request, $redactor);

    expect($event->task)
      ->toBe($redactor->redactText('Test task'))
      ->and($event->metadataKeys)
      ->toContain('[redacted-key]')
      ->and($event->metadataKeys)->toContain('safe_key');
});

it('constructs OrchestrationStarted with list-shaped input and metadata gracefully', function () {
    $event = OrchestrationStarted::fromRequest(
        'orch-id-3',
        new OrchestrationRequest(
            entryAgent: 'test.agent',
            task: 'Test task',
            input: ['first', 'second'],
            metadata: ['meta'],
        ),
        app(Redactor::class),
    );

    expect($event->inputKeys)
      ->toBe([])
      ->and($event->metadataKeys)->toBe([]);
});

it('constructs OrchestrationCompleted from result correctly', function () {
    $trace = [
      new ExecutionTraceRecord(
          orchestrationId: 'orch-1',
          executionId: 'exec-1',
          parentExecutionId: null,
          agentKey: 'agent.one',
          providerProfile: 'openai-default',
          resultKind: 'complete',
          targetAgent: null,
          summary: 'Done',
          metadata: ['task' => 'do stuff'],
      ),
    ];

    $result = new OrchestrationResult(
        orchestrationId: 'orch-1',
        status: 'completed',
        finalAgent: 'agent.one',
        finalExecutionId: 'exec-1',
        finalOutput: ['answer' => 'hello', 'score' => 42],
        summary: 'All done',
        trace: $trace,
    );

    $event = OrchestrationCompleted::fromResult($result);

    expect($event->orchestrationId)
      ->toBe('orch-1')
      ->and($event->status)->toBe('completed')
      ->and($event->finalAgent)->toBe('agent.one')
      ->and($event->traceCount)->toBe(1)
      ->and($event->finalOutputKeys)->toBe(['answer', 'score'])
      ->and($event->trace[0]['agent_key'])->toBe('agent.one')
      ->and($event->summary)->toBe('All done')
      ->and($event->trace[0]['metadata_keys'])->toBe(['task']);
});

it('constructs OrchestrationCompleted with redaction applied', function () {
    $redactor = app(Redactor::class);

    $result = new OrchestrationResult(
        orchestrationId: 'orch-2',
        status: 'completed',
        finalAgent: 'agent.one',
        finalExecutionId: 'exec-2',
        finalOutput: ['answer' => 'hello'],
        summary: 'Summary with secret token',
        trace: [],
    );

    $event = OrchestrationCompleted::fromResult($result, $redactor);

    expect($event->summary)->toBe($redactor->redactText('Summary with secret token'));
});

it('constructs OrchestrationCompleted with list-shaped final output gracefully', function () {
    $event = OrchestrationCompleted::fromResult(
        new OrchestrationResult(
            orchestrationId: 'orch-3',
            status: 'completed',
            finalAgent: 'agent.one',
            finalExecutionId: 'exec-3',
            finalOutput: ['first', 'second'],
            summary: 'Done',
            trace: [],
        ),
        app(Redactor::class),
    );

    expect($event->finalOutputKeys)->toBe([]);
});

it('constructs OrchestrationDelegated from trace correctly', function () {
    $trace = new ExecutionTraceRecord(
        orchestrationId: 'orch-1',
        executionId: 'exec-1',
        parentExecutionId: null,
        agentKey: 'support.agent',
        providerProfile: 'openai-support',
        resultKind: 'delegate',
        targetAgent: 'refund.agent',
        summary: 'Delegating to refund',
        metadata: [
        'task' => 'handle refund',
        'delegation_mode' => 'delegate_and_resume',
        'policy_mode' => 'static_only',
        'policy_rewritten' => false,
        'proposed_target_agent' => 'billing.agent',
      ],
    );

    $event = OrchestrationDelegated::fromTrace($trace);

    expect($event->orchestrationId)
      ->toBe('orch-1')
      ->and($event->agentKey)->toBe('support.agent')
      ->and($event->targetAgent)->toBe('refund.agent')
      ->and($event->delegationMode)->toBe('delegate_and_resume')
      ->and($event->policyMode)->toBe('static_only')
      ->and($event->policyRewritten)->toBeFalse()
      ->and($event->proposedTargetAgent)->toBe('billing.agent')
      ->and($event->traceMetadataKeys)->toContain('task')
      ->and($event->traceMetadataKeys)->toContain('delegation_mode');
});

it('constructs OrchestrationDelegated with missing metadata gracefully', function () {
    $trace = new ExecutionTraceRecord(
        orchestrationId: 'orch-1',
        executionId: 'exec-1',
        parentExecutionId: null,
        agentKey: 'support.agent',
        providerProfile: 'openai-support',
        resultKind: 'delegate',
        targetAgent: null,
        summary: 'Delegating',
        metadata: [],
    );

    $event = OrchestrationDelegated::fromTrace($trace);

    expect($event->targetAgent)
      ->toBe('[missing-target]')
      ->and($event->delegationMode)->toBe('[unknown]')
      ->and($event->policyMode)->toBe('[unknown]')
      ->and($event->policyRewritten)->toBeFalse()
      ->and($event->proposedTargetAgent)->toBeNull();
});

it('constructs OrchestrationFailed with redaction applied', function () {
    $redactor = app(Redactor::class);

    $event = new OrchestrationFailed(
        orchestrationId: 'orch-fail-1',
        entryAgent: 'test.agent',
        task: 'Process api_token secret data',
        exceptionClass: RuntimeException::class,
        exceptionMessage: 'Failed with api_token leak',
        conversationId: 'conv-1',
        redactor: $redactor,
    );

    expect($event->orchestrationId)
      ->toBe('orch-fail-1')
      ->and($event->entryAgent)->toBe('test.agent')
      ->and($event->task)->toBe($redactor->redactText('Process api_token secret data'))
      ->and($event->exceptionClass)->toBe(RuntimeException::class)
      ->and($event->conversationId)->toBe('conv-1')
      ->and($event->exceptionMessage)->toBeString();
});

it('constructs OrchestrationFailed for logical failures without exception details', function () {
    $redactor = app(Redactor::class);

    $event = new OrchestrationFailed(
        orchestrationId: 'orch-fail-3',
        entryAgent: 'failing.agent',
        task: 'Process api_token secret data',
        exceptionClass: null,
        exceptionMessage: null,
        conversationId: 'conv-3',
        failureReason: 'Logical failure with api_token leak',
        redactor: $redactor,
    );

    expect($event->exceptionClass)
      ->toBeNull()
      ->and($event->exceptionMessage)->toBeNull()
      ->and($event->failureReason)->toBe($redactor->redactText('Logical failure with api_token leak'))
      ->and($event->status)->toBe(OrchestrationResult::STATUS_FAILED);
});

it('constructs OrchestrationFailed without redactor', function () {
    $event = new OrchestrationFailed(
        orchestrationId: 'orch-fail-2',
        entryAgent: 'test.agent',
        task: 'Some task',
        exceptionClass: RuntimeException::class,
        exceptionMessage: 'Something went wrong',
        conversationId: null,
    );

    expect($event->task)
      ->toBe('Some task')
      ->and($event->exceptionMessage)->toBe('Something went wrong')
      ->and($event->conversationId)->toBeNull();
});
