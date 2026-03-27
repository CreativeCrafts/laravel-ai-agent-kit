<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\AgentRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\Exceptions\InvalidDelegationTargetException;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\Exceptions\OrchestrationDepthExceededException;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\SynchronousAgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents\OrchestratorContinueAgent;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents\OrchestratorGreetingAgent;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents\OrchestratorHistoryMetadataProbeAgent;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents\OrchestratorInvalidDelegationAgent;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents\OrchestratorLoopAAgent;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents\OrchestratorLoopBAgent;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents\OrchestratorNullNoteDelegatorAgent;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents\OrchestratorPayloadOnlyDelegatorAgent;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents\OrchestratorRefundAgent;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents\OrchestratorSupportAgent;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fixtures\Agents\OrchestratorTransferAgent;

beforeEach(function (): void {
    $registry = app(AgentRegistry::class);
    $registry->registerMany([
      OrchestratorGreetingAgent::class,
      OrchestratorRefundAgent::class,
      OrchestratorSupportAgent::class,
      OrchestratorTransferAgent::class,
      OrchestratorContinueAgent::class,
      OrchestratorInvalidDelegationAgent::class,
      OrchestratorLoopAAgent::class,
      OrchestratorLoopBAgent::class,
      OrchestratorPayloadOnlyDelegatorAgent::class,
      OrchestratorNullNoteDelegatorAgent::class,
      OrchestratorHistoryMetadataProbeAgent::class,
    ]);
});

it('runs a single agent orchestration and returns a final orchestration result', function () {
    $result = (new SynchronousAgentOrchestrator(
        agentRegistry: app(AgentRegistry::class),
    ))->run(
        new OrchestrationRequest(
            entryAgent: 'greeting.agent',
            task: 'Greet the caller',
            input: ['name' => 'Taylor'],
        ),
    );

    expect($result)
      ->toBeInstanceOf(OrchestrationResult::class)
      ->and($result->completed())->toBeTrue()
      ->and($result->finalAgent)->toBe('greeting.agent')
      ->and($result->finalOutput)->toBe([
        'message' => 'Hello Taylor',
        'provider_profile' => 'openai-greeting',
      ])
      ->and($result->trace)->toHaveCount(1)
      ->and($result->trace[0]->agentKey)->toBe('greeting.agent')
      ->and($result->trace[0]->parentExecutionId)->toBeNull();
});

it('builds an execution tree for delegate and resume orchestration flows', function () {
    $result = (new SynchronousAgentOrchestrator(
        agentRegistry: app(AgentRegistry::class),
    ))->run(
        new OrchestrationRequest(
            entryAgent: 'support.agent',
            task: 'Handle the support workflow',
            input: ['subscription_id' => 'sub-123'],
        ),
    );

    expect($result->completed())
      ->toBeTrue()
      ->and($result->finalAgent)->toBe('support.agent')
      ->and($result->finalOutput['workflow'])->toBe('support_refund')
      ->and($result->finalOutput['delegated_agent'])->toBe('refund.agent')
      ->and($result->trace)->toHaveCount(3)
      ->and($result->trace[0]->agentKey)->toBe('support.agent')
      ->and($result->trace[0]->resultKind)->toBe('delegate')
      ->and($result->trace[0]->targetAgent)->toBe('refund.agent')
      ->and($result->trace[1]->agentKey)->toBe('refund.agent')
      ->and($result->trace[1]->parentExecutionId)->toBe($result->trace[0]->executionId)
      ->and($result->trace[2]->agentKey)->toBe('support.agent')
      ->and($result->trace[2]->parentExecutionId)->toBe($result->trace[1]->executionId);
});

it('returns the delegated agent as the final owner when control is transferred', function () {
    $result = (new SynchronousAgentOrchestrator(
        agentRegistry: app(AgentRegistry::class),
    ))->run(
        new OrchestrationRequest(
            entryAgent: 'transfer.agent',
            task: 'Transfer the workflow to the refund specialist',
            input: ['subscription_id' => 'sub-456'],
        ),
    );

    expect($result->completed())
      ->toBeTrue()
      ->and($result->finalAgent)->toBe('refund.agent')
      ->and($result->finalOutput['refund_status'])->toBe('initiated')
      ->and($result->trace)->toHaveCount(2)
      ->and($result->trace[0]->agentKey)->toBe('transfer.agent')
      ->and($result->trace[0]->resultKind)->toBe('delegate')
      ->and($result->trace[1]->agentKey)->toBe('refund.agent')
      ->and($result->trace[1]->parentExecutionId)->toBe($result->trace[0]->executionId);
});

it('supports continue results by reinvoking the same agent with updated context', function () {
    $result = (new SynchronousAgentOrchestrator(
        agentRegistry: app(AgentRegistry::class),
    ))->run(
        new OrchestrationRequest(
            entryAgent: 'continue.agent',
            task: 'Continue the workflow',
        ),
    );

    expect($result->completed())
      ->toBeTrue()
      ->and($result->finalAgent)->toBe('continue.agent')
      ->and($result->finalOutput)->toBe([
        'continued' => true,
        'step' => 2,
        'message' => 'Continue the same agent workflow.',
      ])
      ->and($result->trace)->toHaveCount(2)
      ->and($result->trace[0]->resultKind)->toBe('continue')
      ->and($result->trace[1]->resultKind)->toBe('complete')
      ->and($result->trace[1]->parentExecutionId)->toBe($result->trace[0]->executionId);
});

it('respects payload-only handoff history mode and does not leak parent metadata to delegated agents', function () {
    $result = (new SynchronousAgentOrchestrator(
        agentRegistry: app(AgentRegistry::class),
    ))->run(
        new OrchestrationRequest(
            entryAgent: 'payload-only-delegator.agent',
            task: 'Validate payload-only metadata scope',
            input: ['probe' => 'payload_only'],
            metadata: [
          'sensitive_key' => 'do-not-leak',
          '_orchestrator.internal_marker' => 'internal-only',
          '_orchestrator.history_summary' => 'Parent summary that should not be forwarded in payload-only mode.',
        ],
        ),
    );

    expect($result->completed())
      ->toBeTrue()
      ->and($result->finalAgent)->toBe('history-metadata-probe.agent')
      ->and($result->finalOutput['payload_probe'])->toBe('payload_only')
      ->and($result->finalOutput['seen_sensitive_key'])->toBe('missing')
      ->and($result->finalOutput['seen_internal_marker'])->toBe('missing')
      ->and($result->finalOutput['seen_delegated_by_agent'])->toBe('payload-only-delegator.agent')
      ->and($result->finalOutput['seen_requested_outcome'])->toBe('Report visible metadata fields.')
      ->and($result->finalOutput['history_summary'])->toBeNull();
});

it('keeps existing orchestrator history summary when delegation note is omitted', function () {
    $result = (new SynchronousAgentOrchestrator(
        agentRegistry: app(AgentRegistry::class),
    ))->run(
        new OrchestrationRequest(
            entryAgent: 'null-note-delegator.agent',
            task: 'Validate null-note summary preservation',
            input: ['probe' => 'null_note'],
            metadata: [
          '_orchestrator.history_summary' => 'Existing summary that must be preserved.',
        ],
        ),
    );

    expect($result->completed())
      ->toBeTrue()
      ->and($result->finalAgent)->toBe('history-metadata-probe.agent')
      ->and($result->finalOutput['payload_probe'])->toBe('null_note')
      ->and($result->finalOutput['seen_delegated_by_agent'])->toBe('null-note-delegator.agent')
      ->and($result->finalOutput['history_summary'])->toBe('Existing summary that must be preserved.');
});

it('throws when an agent delegates to a target that is not allowed by its definition', function () {
    (new SynchronousAgentOrchestrator(
        agentRegistry: app(AgentRegistry::class),
    ))->run(
        new OrchestrationRequest(
            entryAgent: 'invalid-delegation.agent',
            task: 'Attempt invalid delegation',
        ),
    );
})->throws(InvalidDelegationTargetException::class, 'Allowed targets: [none]');

it('throws when recursive delegation exceeds the configured execution depth', function () {
    (new SynchronousAgentOrchestrator(
        agentRegistry: app(AgentRegistry::class),
        maxExecutionDepth: 4,
    ))->run(
        new OrchestrationRequest(
            entryAgent: 'loop-a.agent',
            task: 'Trigger a delegation loop',
        ),
    );
})->throws(OrchestrationDepthExceededException::class, 'maximum depth [4]');
