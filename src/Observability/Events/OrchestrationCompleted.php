<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\Redactor;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\ExecutionTraceRecord;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationResult;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\Concerns\ExtractsRedactedKeys;

final readonly class OrchestrationCompleted
{
    use ExtractsRedactedKeys;

    public string $summary;

    /**
     * @param list<string> $finalOutputKeys
     * @param list<array{
     *   execution_id: string,
     *   parent_execution_id: ?string,
     *   agent_key: string,
     *   provider_profile: string,
     *   result_kind: string,
     *   target_agent: ?string,
     *   metadata_keys: list<string>
     * }> $trace
     */
    public function __construct(
        public string $orchestrationId,
        public string $status,
        public string $finalAgent,
        public string $finalExecutionId,
        string $summary,
        public int $traceCount,
        public array $finalOutputKeys,
        public array $trace,
        ?Redactor $redactor = null,
    ) {
        $this->summary = $redactor instanceof Redactor
          ? $redactor->redactText($summary)
          : $summary;
    }

    public static function fromResult(OrchestrationResult $result, ?Redactor $redactor = null): self
    {
        return new self(
            orchestrationId: $result->orchestrationId,
            status: $result->status,
            finalAgent: $result->finalAgent,
            finalExecutionId: $result->finalExecutionId,
            summary: $result->summary,
            traceCount: count($result->trace),
            finalOutputKeys: self::keys($result->finalOutput, $redactor),
            trace: array_map(
                static fn (ExecutionTraceRecord $trace): array
                => [
              'execution_id' => $trace->executionId,
              'parent_execution_id' => $trace->parentExecutionId,
              'agent_key' => $trace->agentKey,
              'provider_profile' => $trace->providerProfile,
              'result_kind' => $trace->resultKind,
              'target_agent' => $trace->targetAgent,
              'metadata_keys' => self::keys($trace->metadata, $redactor),
            ],
                $result->trace,
            ),
            redactor: $redactor,
        );
    }
}
