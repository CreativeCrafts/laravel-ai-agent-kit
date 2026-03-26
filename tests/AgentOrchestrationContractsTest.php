<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\DelegationProposal;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\ExecutionTraceRecord;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\HandoffPayload;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationResult;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;

it('defines agent metadata with explicit provider and delegation rules', function () {
    $definition = new AgentDefinition(
        key: 'support.agent',
        displayName: 'Support Agent',
        requiredCapabilities: ['text_generation', 'structured_output'],
        primaryProviderProfile: 'anthropic-support',
        fallbackProviderProfiles: ['openai-support'],
        delegationTargets: ['cancellation.agent', 'refund.agent'],
    );

    expect($definition->providerProfiles())
      ->toBe(['anthropic-support', 'openai-support'])
      ->and($definition->requiresCapability('text_generation'))->toBeTrue()
      ->and($definition->allowsDelegationTo('refund.agent'))->toBeTrue()
      ->and($definition->allowsDelegationTo('billing.agent'))->toBeFalse();
});

it('rejects invalid agent definition values', function () {
    expect(fn () => new AgentDefinition(
        key: '',
        displayName: 'Support Agent',
        requiredCapabilities: [],
        primaryProviderProfile: 'anthropic-support',
    ))
      ->toThrow(InvalidArgumentException::class, 'non-empty key')
      ->and(fn () => new AgentDefinition(
          key: 'support.agent',
          displayName: 'Support Agent',
          requiredCapabilities: ['text_generation', 'text_generation'],
          primaryProviderProfile: 'anthropic-support',
      ))->toThrow(InvalidArgumentException::class, 'requiredCapabilities entries must be unique')
      ->and(fn () => new AgentDefinition(
          key: 'support.agent',
          displayName: 'Support Agent',
          requiredCapabilities: [],
          primaryProviderProfile: 'anthropic-support',
          fallbackProviderProfiles: ['anthropic-support'],
      ))->toThrow(InvalidArgumentException::class, 'must not include the primaryProviderProfile');
});

it('captures an agent execution context with payload metadata and history summary', function () {
    $context = new AgentExecutionContext(
        orchestrationId: 'orch-001',
        executionId: 'exec-001',
        parentExecutionId: 'exec-000',
        agent: new AgentDefinition(
            key: 'support.agent',
            displayName: 'Support Agent',
            requiredCapabilities: ['text_generation'],
            primaryProviderProfile: 'anthropic-support',
        ),
        providerProfile: 'anthropic-support',
        task: 'Handle cancellation request',
        payload: ['customer_id' => 42, 'message' => 'Please cancel my subscription.'],
        metadata: ['channel' => 'email'],
        historySummary: 'Customer requested cancellation in the previous step.',
    );

    expect($context->hasParentExecution())
      ->toBeTrue()
      ->and($context->payloadValue('customer_id'))->toBe(42)
      ->and($context->metadataValue('channel'))->toBe('email');
});

it('defines structured-first handoff payloads and explicit delegation proposals', function () {
    $handoff = new HandoffPayload(
        task: 'Cancel the active subscription.',
        reason: 'customer_requested_cancellation',
        payload: ['subscription_id' => 'sub-123'],
        historyMode: HandoffPayload::HISTORY_PAYLOAD_PLUS_SUMMARY,
        note: 'The customer requested immediate cancellation and wants confirmation.',
        requestedOutcome: 'Cancellation completed and status returned.',
    );

    $proposal = new DelegationProposal(
        mode: DelegationProposal::MODE_TRANSFER_CONTROL,
        targetAgent: 'cancellation.agent',
        handoff: $handoff,
    );

    expect($handoff->sharesFullHistory())
      ->toBeFalse()
      ->and($proposal->transfersControl())->toBeTrue();
});

it('rejects invalid handoff payload and delegation proposal values', function () {
    expect(fn () => new HandoffPayload(
        task: '',
        reason: 'customer_requested_cancellation',
    ))
      ->toThrow(InvalidArgumentException::class, 'non-empty task')
      ->and(fn () => new HandoffPayload(
          task: 'Cancel the active subscription.',
          reason: 'customer_requested_cancellation',
          historyMode: 'everything',
      ))->toThrow(InvalidArgumentException::class, 'historyMode must be one of')
      ->and(fn () => new DelegationProposal(
          mode: 'handoff_now',
          targetAgent: 'cancellation.agent',
          handoff: new HandoffPayload(
              task: 'Cancel the active subscription.',
              reason: 'customer_requested_cancellation',
          ),
      ))->toThrow(InvalidArgumentException::class, 'delegate_and_resume or transfer_control');
});

it('defines agent execution results for completion and delegated execution', function () {
    $complete = new AgentExecutionResult(
        kind: AgentExecutionResult::KIND_COMPLETE,
        output: ['status' => 'completed'],
        summary: 'Support workflow completed successfully.',
    );

    $delegate = new AgentExecutionResult(
        kind: AgentExecutionResult::KIND_DELEGATE,
        delegation: new DelegationProposal(
            mode: DelegationProposal::MODE_DELEGATE_AND_RESUME,
            targetAgent: 'refund.agent',
            handoff: new HandoffPayload(
                task: 'Evaluate refund eligibility.',
                reason: 'cancellation_complete',
                payload: ['subscription_id' => 'sub-123'],
            ),
        ),
        summary: 'Delegating refund eligibility to a specialist agent.',
    );

    expect($complete->isTerminal())
      ->toBeTrue()
      ->and($delegate->isTerminal())->toBeFalse()
      ->and($delegate->delegation?->mode)->toBe(DelegationProposal::MODE_DELEGATE_AND_RESUME);
});

it('rejects invalid agent execution result combinations', function () {
    expect(fn () => new AgentExecutionResult(
        kind: AgentExecutionResult::KIND_DELEGATE,
        summary: 'Missing delegation proposal.',
    ))
      ->toThrow(InvalidArgumentException::class, 'require a delegation proposal')
      ->and(fn () => new AgentExecutionResult(
          kind: AgentExecutionResult::KIND_COMPLETE,
          delegation: new DelegationProposal(
              mode: DelegationProposal::MODE_DELEGATE_AND_RESUME,
              targetAgent: 'refund.agent',
              handoff: new HandoffPayload(
                  task: 'Evaluate refund eligibility.',
                  reason: 'cancellation_complete',
              ),
          ),
      ))->toThrow(InvalidArgumentException::class, 'may only carry a delegation proposal');
});

it('defines orchestration request execution trace and final orchestration result types', function () {
    $request = new OrchestrationRequest(
        entryAgent: 'support.agent',
        task: 'Handle cancellation and refund workflow',
        input: ['message' => 'Please cancel and refund my subscription.'],
        metadata: ['channel' => 'chat'],
        conversationId: new ConversationId('conv-001'),
    );

    $trace = new ExecutionTraceRecord(
        orchestrationId: 'orch-001',
        executionId: 'exec-002',
        parentExecutionId: 'exec-001',
        agentKey: 'refund.agent',
        providerProfile: 'openai-refund',
        resultKind: AgentExecutionResult::KIND_COMPLETE,
        targetAgent: null,
        summary: 'Refund workflow completed successfully.',
        metadata: ['amount' => 4999],
    );

    $result = new OrchestrationResult(
        orchestrationId: 'orch-001',
        status: OrchestrationResult::STATUS_COMPLETED,
        finalAgent: 'refund.agent',
        finalExecutionId: 'exec-002',
        finalOutput: ['refund_status' => 'initiated'],
        summary: 'Cancellation completed and refund initiated.',
        trace: [$trace],
    );

    expect($request->inputValue('message'))
      ->toBe('Please cancel and refund my subscription.')
      ->and($request->metadataValue('channel'))->toBe('chat')
      ->and($trace->hasParentExecution())->toBeTrue()
      ->and($result->completed())->toBeTrue()
      ->and($result->trace)->toHaveCount(1);
});

it('rejects invalid orchestration request trace and result values', function () {
    expect(fn () => new OrchestrationRequest(
        entryAgent: '',
        task: 'Handle cancellation workflow',
    ))
      ->toThrow(InvalidArgumentException::class, 'non-empty entryAgent')
      ->and(fn () => new ExecutionTraceRecord(
          orchestrationId: 'orch-001',
          executionId: '',
          parentExecutionId: null,
          agentKey: 'support.agent',
          providerProfile: 'anthropic-support',
          resultKind: AgentExecutionResult::KIND_COMPLETE,
      ))->toThrow(InvalidArgumentException::class, 'non-empty executionId')
      ->and(fn () => new OrchestrationResult(
          orchestrationId: 'orch-001',
          status: 'running',
          finalAgent: 'support.agent',
          finalExecutionId: 'exec-001',
          finalOutput: [],
          summary: 'Workflow still running.',
      ))->toThrow(InvalidArgumentException::class, 'status must be completed, failed, or cancelled');
});
