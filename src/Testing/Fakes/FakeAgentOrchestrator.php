<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Testing\Fakes;

use Closure;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\ExecutionTraceRecord;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationResult;
use Throwable;

final class FakeAgentOrchestrator implements AgentOrchestrator
{
    /**
     * @var list<OrchestrationResult|Throwable|Closure(OrchestrationRequest): OrchestrationResult>
     */
    private array $queuedResponses = [];

    /**
     * @var list<OrchestrationRequest>
     */
    private array $requests = [];

    /**
     * @param iterable<OrchestrationResult|Throwable|Closure(OrchestrationRequest): OrchestrationResult> $queuedResponses
     */
    public function __construct(iterable $queuedResponses = [])
    {
        foreach ($queuedResponses as $queuedResponse) {
            $this->queuedResponses[] = $queuedResponse;
        }
    }

    public function run(OrchestrationRequest $request): OrchestrationResult
    {
        $this->requests[] = $request;

        $queuedResponse = array_shift($this->queuedResponses);

        if ($queuedResponse instanceof Throwable) {
            throw $queuedResponse;
        }

        if ($queuedResponse instanceof OrchestrationResult) {
            return $queuedResponse;
        }

        if ($queuedResponse instanceof Closure) {
            return $queuedResponse($request);
        }

        $sequence = count($this->requests);
        $orchestrationId = sprintf('fake-orchestration-%03d', $sequence);
        $executionId = sprintf('fake-execution-%03d', $sequence);

        return new OrchestrationResult(
            orchestrationId: $orchestrationId,
            status: OrchestrationResult::STATUS_COMPLETED,
            finalAgent: $request->entryAgent,
            finalExecutionId: $executionId,
            finalOutput: [
            'fake_orchestrator' => true,
            'task' => $request->task,
          ],
            summary: sprintf('Fake orchestration completed for [%s].', $request->entryAgent),
            trace: [
            new ExecutionTraceRecord(
                orchestrationId: $orchestrationId,
                executionId: $executionId,
                parentExecutionId: null,
                agentKey: $request->entryAgent,
                providerProfile: 'fake-profile',
                resultKind: 'complete',
                summary: sprintf('Completed fake orchestration task [%s].', $request->task),
            ),
          ],
        );
    }

    public function queueFailure(Throwable $throwable): self
    {
        $this->queuedResponses[] = $throwable;

        return $this;
    }

    /**
     * @param array<string, mixed> $finalOutput
     */
    public function queueCompletedResult(
        string $finalAgent,
        string $summary,
        array $finalOutput = [],
        ?string $providerProfile = null,
    ): self {
        return $this->queueCallback(function (OrchestrationRequest $request) use ($finalAgent, $summary, $finalOutput, $providerProfile): OrchestrationResult {
            $sequence = count($this->requests);
            $orchestrationId = sprintf('fake-orchestration-%03d', $sequence);
            $executionId = sprintf('fake-execution-%03d', $sequence);

            return new OrchestrationResult(
                orchestrationId: $orchestrationId,
                status: OrchestrationResult::STATUS_COMPLETED,
                finalAgent: $finalAgent,
                finalExecutionId: $executionId,
                finalOutput: $finalOutput,
                summary: $summary,
                trace: [
                new ExecutionTraceRecord(
                    orchestrationId: $orchestrationId,
                    executionId: $executionId,
                    parentExecutionId: null,
                    agentKey: $finalAgent,
                    providerProfile: $providerProfile ?? 'fake-profile',
                    resultKind: 'complete',
                    summary: $summary,
                ),
              ],
            );
        });
    }

    /**
     * @param Closure(OrchestrationRequest): OrchestrationResult $callback
     */
    public function queueCallback(Closure $callback): self
    {
        $this->queuedResponses[] = $callback;

        return $this;
    }

    public function queueResult(OrchestrationResult $result): self
    {
        $this->queuedResponses[] = $result;

        return $this;
    }

    /**
     * @param array<string, mixed> $finalOutput
     */
    public function queueDelegationFlowResult(
        string $sourceAgent,
        string $targetAgent,
        string $handoffSummary,
        array $finalOutput = [],
    ): self {
        return $this->queueCallback(function (OrchestrationRequest $request) use ($sourceAgent, $targetAgent, $handoffSummary, $finalOutput): OrchestrationResult {
            $sequence = count($this->requests);
            $orchestrationId = sprintf('fake-orchestration-%03d', $sequence);
            $delegateExecutionId = sprintf('fake-execution-%03d-a', $sequence);
            $delegatedExecutionId = sprintf('fake-execution-%03d-b', $sequence);
            $resumeExecutionId = sprintf('fake-execution-%03d-c', $sequence);

            return new OrchestrationResult(
                orchestrationId: $orchestrationId,
                status: OrchestrationResult::STATUS_COMPLETED,
                finalAgent: $sourceAgent,
                finalExecutionId: $resumeExecutionId,
                finalOutput: $finalOutput,
                summary: sprintf('Delegation flow completed for [%s] via [%s].', $sourceAgent, $targetAgent),
                trace: [
                new ExecutionTraceRecord(
                    orchestrationId: $orchestrationId,
                    executionId: $delegateExecutionId,
                    parentExecutionId: null,
                    agentKey: $sourceAgent,
                    providerProfile: 'fake-source-profile',
                    resultKind: 'delegate',
                    targetAgent: $targetAgent,
                    summary: $handoffSummary,
                    metadata: [
                    'policy_mode' => 'static_only',
                    'policy_rewritten' => false,
                  ],
                ),
                new ExecutionTraceRecord(
                    orchestrationId: $orchestrationId,
                    executionId: $delegatedExecutionId,
                    parentExecutionId: $delegateExecutionId,
                    agentKey: $targetAgent,
                    providerProfile: 'fake-target-profile',
                    resultKind: 'complete',
                    summary: sprintf('Delegated agent [%s] completed the handoff task.', $targetAgent),
                ),
                new ExecutionTraceRecord(
                    orchestrationId: $orchestrationId,
                    executionId: $resumeExecutionId,
                    parentExecutionId: $delegatedExecutionId,
                    agentKey: $sourceAgent,
                    providerProfile: 'fake-source-profile',
                    resultKind: 'complete',
                    summary: sprintf('Source agent [%s] resumed after delegation.', $sourceAgent),
                ),
              ],
            );
        });
    }

    /**
     * @param array<string, mixed> $finalOutput
     */
    public function queueTransferredResult(
        string $sourceAgent,
        string $targetAgent,
        string $handoffSummary,
        array $finalOutput = [],
    ): self {
        return $this->queueCallback(function (OrchestrationRequest $request) use ($sourceAgent, $targetAgent, $handoffSummary, $finalOutput): OrchestrationResult {
            $sequence = count($this->requests);
            $orchestrationId = sprintf('fake-orchestration-%03d', $sequence);
            $delegateExecutionId = sprintf('fake-execution-%03d-a', $sequence);
            $transferredExecutionId = sprintf('fake-execution-%03d-b', $sequence);

            return new OrchestrationResult(
                orchestrationId: $orchestrationId,
                status: OrchestrationResult::STATUS_COMPLETED,
                finalAgent: $targetAgent,
                finalExecutionId: $transferredExecutionId,
                finalOutput: $finalOutput,
                summary: sprintf('Control transferred from [%s] to [%s].', $sourceAgent, $targetAgent),
                trace: [
                new ExecutionTraceRecord(
                    orchestrationId: $orchestrationId,
                    executionId: $delegateExecutionId,
                    parentExecutionId: null,
                    agentKey: $sourceAgent,
                    providerProfile: 'fake-source-profile',
                    resultKind: 'delegate',
                    targetAgent: $targetAgent,
                    summary: $handoffSummary,
                    metadata: [
                    'policy_mode' => 'static_only',
                    'policy_rewritten' => false,
                    'transfer_control' => true,
                  ],
                ),
                new ExecutionTraceRecord(
                    orchestrationId: $orchestrationId,
                    executionId: $transferredExecutionId,
                    parentExecutionId: $delegateExecutionId,
                    agentKey: $targetAgent,
                    providerProfile: 'fake-target-profile',
                    resultKind: 'complete',
                    summary: sprintf('Transferred agent [%s] became final owner.', $targetAgent),
                ),
              ],
            );
        });
    }

    /**
     * @return list<OrchestrationRequest>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    public function lastRequest(): ?OrchestrationRequest
    {
        $lastRequestIndex = array_key_last($this->requests);

        return $lastRequestIndex !== null ? $this->requests[$lastRequestIndex] : null;
    }

    public function reset(): void
    {
        $this->queuedResponses = [];
        $this->requests = [];
    }
}
