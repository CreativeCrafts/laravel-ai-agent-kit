<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Orchestration;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\AgentRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\DelegationPolicyEngine;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\Exceptions\OrchestrationDepthExceededException;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\Exceptions\OrchestrationStepLimitExceededException;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\Exceptions\UnsupportedAgentExecutionResultException;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class SynchronousAgentOrchestrator implements AgentOrchestrator
{
    private const string META_HISTORY_SUMMARY = '_orchestrator.history_summary';
    private const string META_CONVERSATION_ID = '_orchestrator.conversation_id';
    private const string META_DELEGATED_BY_AGENT = '_orchestrator.delegated_by_agent';
    private const string META_REQUESTED_OUTCOME = '_orchestrator.requested_outcome';
    private const string META_CONTINUED_FROM_EXECUTION_ID = '_orchestrator.continued_from_execution_id';
    private const string META_RESUMED_AFTER_DELEGATE = '_orchestrator.resumed_after_delegate';
    private const string META_DELEGATED_AGENT = '_orchestrator.delegated_agent';
    private const string META_DELEGATED_EXECUTION_ID = '_orchestrator.delegated_execution_id';
    private const string META_DELEGATED_STATUS = '_orchestrator.delegated_status';

    /**
     * @var list<string>
     */
    private const array SUPPORTED_RESULT_KINDS = [
      AgentExecutionResult::KIND_COMPLETE,
      AgentExecutionResult::KIND_CONTINUE,
      AgentExecutionResult::KIND_DELEGATE,
      AgentExecutionResult::KIND_FAIL,
    ];

    public function __construct(
        private AgentRegistry $agentRegistry,
        private ?DelegationPolicyEngine $delegationPolicyEngine = null,
        private int $maxExecutionDepth = 25,
        private int $maxExecutionSteps = 50,
    ) {
        if ($this->maxExecutionDepth < 1) {
            throw new InvalidArgumentException('SynchronousAgentOrchestrator maxExecutionDepth must be greater than zero.');
        }

        if ($this->maxExecutionSteps < 1) {
            throw new InvalidArgumentException('SynchronousAgentOrchestrator maxExecutionSteps must be greater than zero.');
        }
    }

    public function run(OrchestrationRequest $request): OrchestrationResult
    {
        $orchestrationId = (string)Str::uuid();
        $trace = [];
        $metadata = $request->metadata;

        if ($request->conversationId instanceof ConversationId) {
            $metadata[self::META_CONVERSATION_ID] = $request->conversationId->toString();
        }

        $outcome = $this->executeAgent(
            orchestrationId: $orchestrationId,
            agentKey: $request->entryAgent,
            task: $request->task,
            payload: $request->input,
            metadata: $metadata,
            parentExecutionId: null,
            depth: 1,
            step: 1,
            trace: $trace,
        );

        return new OrchestrationResult(
            orchestrationId: $orchestrationId,
            status: $outcome['status'],
            finalAgent: $outcome['final_agent'],
            finalExecutionId: $outcome['final_execution_id'],
            finalOutput: $outcome['final_output'],
            summary: $outcome['summary'],
            trace: $trace,
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $metadata
     * @param list<ExecutionTraceRecord> $trace
     * @return array{
     *   status: string,
     *   final_agent: string,
     *   final_execution_id: string,
     *   final_output: array<string, mixed>,
     *   summary: string,
     *   step_count: int
     * }
     */
    private function executeAgent(
        string $orchestrationId,
        string $agentKey,
        string $task,
        array $payload,
        array $metadata,
        ?string $parentExecutionId,
        int $depth,
        int $step,
        array &$trace,
    ): array {
        $this->guardExecutionLimits($agentKey, $depth, $step);

        $agent = $this->agentRegistry->get($agentKey);
        $definition = $agent->definition();
        $executionId = (string)Str::uuid();

        $result = $agent->handle(
            new AgentExecutionContext(
                orchestrationId: $orchestrationId,
                executionId: $executionId,
                parentExecutionId: $parentExecutionId,
                agent: $definition,
                providerProfile: $definition->primaryProviderProfile,
                task: $task,
                payload: $payload,
                metadata: $metadata,
                historySummary: $this->historySummary($metadata),
            ),
        );

        return match ($result->kind) {
            AgentExecutionResult::KIND_COMPLETE,
            AgentExecutionResult::KIND_FAIL => $this->handleTerminalResult(
                orchestrationId: $orchestrationId,
                parentExecutionId: $parentExecutionId,
                task: $task,
                definitionKey: $definition->key,
                providerProfile: $definition->primaryProviderProfile,
                executionId: $executionId,
                step: $step,
                result: $result,
                trace: $trace,
            ),
            AgentExecutionResult::KIND_CONTINUE => $this->handleContinueResult(
                orchestrationId: $orchestrationId,
                task: $task,
                payload: $payload,
                metadata: $metadata,
                parentExecutionId: $parentExecutionId,
                depth: $depth,
                step: $step,
                definitionKey: $definition->key,
                providerProfile: $definition->primaryProviderProfile,
                executionId: $executionId,
                result: $result,
                trace: $trace,
            ),
            AgentExecutionResult::KIND_DELEGATE => $this->handleDelegationResult(
                orchestrationId: $orchestrationId,
                task: $task,
                payload: $payload,
                metadata: $metadata,
                parentExecutionId: $parentExecutionId,
                depth: $depth,
                step: $step,
                definition: $definition,
                providerProfile: $definition->primaryProviderProfile,
                executionId: $executionId,
                result: $result,
                trace: $trace,
            ),
            default => throw UnsupportedAgentExecutionResultException::forKind(
                agentKey: $definition->key,
                kind: $result->kind,
                supportedKinds: self::SUPPORTED_RESULT_KINDS,
            ),
        };
    }

    private function guardExecutionLimits(string $agentKey, int $depth, int $step): void
    {
        if ($depth > $this->maxExecutionDepth) {
            throw OrchestrationDepthExceededException::forAgent(
                agentKey: $agentKey,
                depth: $depth,
                maxDepth: $this->maxExecutionDepth,
            );
        }

        if ($step > $this->maxExecutionSteps) {
            throw OrchestrationStepLimitExceededException::forAgent(
                agentKey: $agentKey,
                step: $step,
                maxSteps: $this->maxExecutionSteps,
            );
        }
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function historySummary(array $metadata): ?string
    {
        $summary = $metadata[self::META_HISTORY_SUMMARY] ?? $metadata['history_summary'] ?? null;

        return is_string($summary) && $summary !== ''
          ? $summary
          : null;
    }

    /**
     * @param list<ExecutionTraceRecord> $trace
     * @return array{
     *   status: string,
     *   final_agent: string,
     *   final_execution_id: string,
     *   final_output: array<string, mixed>,
     *   summary: string,
     *   step_count: int
     * }
     */
    private function handleTerminalResult(
        string $orchestrationId,
        ?string $parentExecutionId,
        string $task,
        string $definitionKey,
        string $providerProfile,
        string $executionId,
        int $step,
        AgentExecutionResult $result,
        array &$trace,
    ): array {
        $trace[] = new ExecutionTraceRecord(
            orchestrationId: $orchestrationId,
            executionId: $executionId,
            parentExecutionId: $parentExecutionId,
            agentKey: $definitionKey,
            providerProfile: $providerProfile,
            resultKind: $result->kind,
            summary: $result->summary,
            metadata: [
            'task' => $task,
          ],
        );

        return [
          'status' => $result->kind === AgentExecutionResult::KIND_COMPLETE
            ? OrchestrationResult::STATUS_COMPLETED
            : OrchestrationResult::STATUS_FAILED,
          'final_agent' => $definitionKey,
          'final_execution_id' => $executionId,
          'final_output' => $result->output,
          'summary' => $result->summary ?? sprintf(
              'Agent [%s] finished with result kind [%s].',
              $definitionKey,
              $result->kind,
          ),
          'step_count' => $step,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $metadata
     * @param list<ExecutionTraceRecord> $trace
     * @return array{
     *   status: string,
     *   final_agent: string,
     *   final_execution_id: string,
     *   final_output: array<string, mixed>,
     *   summary: string,
     *   step_count: int
     * }
     */
    private function handleContinueResult(
        string $orchestrationId,
        string $task,
        array $payload,
        array $metadata,
        ?string $parentExecutionId,
        int $depth,
        int $step,
        string $definitionKey,
        string $providerProfile,
        string $executionId,
        AgentExecutionResult $result,
        array &$trace,
    ): array {
        $continueMetadata = array_merge($metadata, [
          self::META_CONTINUED_FROM_EXECUTION_ID => $executionId,
        ]);

        if (is_string($result->summary) && $result->summary !== '') {
            $continueMetadata[self::META_HISTORY_SUMMARY] = $result->summary;
        }

        $trace[] = new ExecutionTraceRecord(
            orchestrationId: $orchestrationId,
            executionId: $executionId,
            parentExecutionId: $parentExecutionId,
            agentKey: $definitionKey,
            providerProfile: $providerProfile,
            resultKind: $result->kind,
            summary: $result->summary,
            metadata: [
            'task' => $task,
            self::META_CONTINUED_FROM_EXECUTION_ID => $executionId,
          ],
        );

        return $this->executeAgent(
            orchestrationId: $orchestrationId,
            agentKey: $definitionKey,
            task: $task,
            payload: array_merge($payload, $result->output),
            metadata: $continueMetadata,
            parentExecutionId: $executionId,
            depth: $depth + 1,
            step: $step + 1,
            trace: $trace,
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $metadata
     * @param list<ExecutionTraceRecord> $trace
     * @return array{
     *   status: string,
     *   final_agent: string,
     *   final_execution_id: string,
     *   final_output: array<string, mixed>,
     *   summary: string,
     *   step_count: int
     * }
     */
    private function handleDelegationResult(
        string $orchestrationId,
        string $task,
        array $payload,
        array $metadata,
        ?string $parentExecutionId,
        int $depth,
        int $step,
        AgentDefinition $definition,
        string $providerProfile,
        string $executionId,
        AgentExecutionResult $result,
        array &$trace,
    ): array {
        $proposal = $result->delegation;

        if (!$proposal instanceof DelegationProposal) {
            throw UnsupportedAgentExecutionResultException::forKind(
                agentKey: $definition->key,
                kind: $result->kind,
                supportedKinds: self::SUPPORTED_RESULT_KINDS,
            );
        }

        $policyDecision = $this->delegationPolicyEngine()->evaluate($definition, $proposal);
        $approvedProposal = $policyDecision->proposal;

        $trace[] = new ExecutionTraceRecord(
            orchestrationId: $orchestrationId,
            executionId: $executionId,
            parentExecutionId: $parentExecutionId,
            agentKey: $definition->key,
            providerProfile: $providerProfile,
            resultKind: $result->kind,
            targetAgent: $approvedProposal->targetAgent,
            summary: $result->summary,
            metadata: [
            'task' => $task,
            'delegation_mode' => $approvedProposal->mode,
            'handoff_task' => $approvedProposal->handoff->task,
            'history_mode' => $approvedProposal->handoff->historyMode,
            'policy_mode' => $policyDecision->mode->value,
            'policy_rewritten' => $policyDecision->rewritten,
            'proposed_target_agent' => $policyDecision->originalTargetAgent,
          ],
        );

        $delegatedOutcome = $this->executeAgent(
            orchestrationId: $orchestrationId,
            agentKey: $approvedProposal->targetAgent,
            task: $approvedProposal->handoff->task,
            payload: $approvedProposal->handoff->payload,
            metadata: $this->childMetadata(
                parentAgentKey: $definition->key,
                proposal: $approvedProposal,
                metadata: $metadata,
            ),
            parentExecutionId: $executionId,
            depth: $depth + 1,
            step: $step + 1,
            trace: $trace,
        );

        if ($approvedProposal->transfersControl()) {
            return $delegatedOutcome;
        }

        return $this->executeAgent(
            orchestrationId: $orchestrationId,
            agentKey: $definition->key,
            task: $task,
            payload: array_merge($payload, [
            'delegated_result' => $delegatedOutcome['final_output'],
            'delegated_agent' => $delegatedOutcome['final_agent'],
            'delegated_execution_id' => $delegatedOutcome['final_execution_id'],
            'delegated_status' => $delegatedOutcome['status'],
          ]),
            metadata: array_merge($metadata, [
            self::META_HISTORY_SUMMARY => $delegatedOutcome['summary'],
            self::META_RESUMED_AFTER_DELEGATE => true,
            self::META_DELEGATED_AGENT => $delegatedOutcome['final_agent'],
            self::META_DELEGATED_EXECUTION_ID => $delegatedOutcome['final_execution_id'],
            self::META_DELEGATED_STATUS => $delegatedOutcome['status'],
          ]),
            parentExecutionId: $delegatedOutcome['final_execution_id'],
            depth: $depth + 1,
            step: $delegatedOutcome['step_count'] + 1,
            trace: $trace,
        );
    }

    private function delegationPolicyEngine(): DelegationPolicyEngine
    {
        return $this->delegationPolicyEngine ?? new ConfigurableDelegationPolicyEngine(
            agentRegistry: $this->agentRegistry,
            mode: DelegationPolicyMode::STATIC_ONLY,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function childMetadata(string $parentAgentKey, DelegationProposal $proposal, array $metadata): array
    {
        $childMetadata = $proposal->handoff->sharesFullHistory()
          ? $metadata
          : [];

        if ($proposal->handoff->historyMode === HandoffPayload::HISTORY_PAYLOAD_PLUS_SUMMARY) {
            $existingSummary = $this->historySummary($metadata);

            if ($existingSummary !== null) {
                $childMetadata[self::META_HISTORY_SUMMARY] = $existingSummary;
            }
        }

        if ($proposal->handoff->note !== null) {
            $childMetadata[self::META_HISTORY_SUMMARY] = $proposal->handoff->note;
        }

        $conversationId = $metadata[self::META_CONVERSATION_ID] ?? null;

        if (is_string($conversationId) && $conversationId !== '') {
            $childMetadata[self::META_CONVERSATION_ID] = $conversationId;
        }

        $childMetadata[self::META_DELEGATED_BY_AGENT] = $parentAgentKey;
        $childMetadata[self::META_REQUESTED_OUTCOME] = $proposal->handoff->requestedOutcome;

        return $childMetadata;
    }
}
