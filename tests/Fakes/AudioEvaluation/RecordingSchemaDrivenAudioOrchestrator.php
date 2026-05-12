<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fakes\AudioEvaluation;

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\AudioToTextToEvaluationCoordinatorAgent;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationResult;

final class RecordingSchemaDrivenAudioOrchestrator implements AgentOrchestrator
{
    /** @var list<OrchestrationRequest> */
    public array $requests = [];

    /**
     * @param array<string, mixed> $finalOutput
     */
    public function __construct(private readonly array $finalOutput)
    {
    }

    public function run(OrchestrationRequest $request): OrchestrationResult
    {
        $this->requests[] = $request;

        return new OrchestrationResult(
            orchestrationId: 'orch-schema-driven-001',
            status: OrchestrationResult::STATUS_COMPLETED,
            finalAgent: AudioToTextToEvaluationCoordinatorAgent::KEY,
            finalExecutionId: 'exec-schema-driven-final',
            finalOutput: $this->finalOutput,
            summary: 'Schema-driven audio evaluation completed.',
        );
    }
}
