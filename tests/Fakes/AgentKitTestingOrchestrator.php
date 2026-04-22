<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fakes;

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\AudioToTextToEvaluationCoordinatorAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\TextToStructuredEvaluationCoordinatorAgent;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationResult;

final class AgentKitTestingOrchestrator implements AgentOrchestrator
{
    /**
     * @var list<OrchestrationRequest>
     */
    public array $requests = [];

    public function run(OrchestrationRequest $request): OrchestrationResult
    {
        $this->requests[] = $request;

        return match ($request->entryAgent) {
            TextToStructuredEvaluationCoordinatorAgent::KEY => new OrchestrationResult(
                orchestrationId: 'orch-text-001',
                status: OrchestrationResult::STATUS_COMPLETED,
                finalAgent: TextToStructuredEvaluationCoordinatorAgent::KEY,
                finalExecutionId: 'exec-text-001',
                finalOutput: [
                'subject' => 'support reply',
                'summary' => 'The request is clear and directly asks for a refund.',
                'recommended_action' => 'Route the request to the refund review workflow.',
                'confidence' => 0.95,
                'enabled_dimensions' => ['clarity', 'accuracy'],
                'dimensions' => [
                  'clarity' => [
                    'score' => 5,
                    'summary' => 'The refund request is explicit.',
                    'evidence' => ['The user directly asks for a refund.'],
                  ],
                  'accuracy' => [
                    'score' => 4,
                    'summary' => 'The request is consistent with a standard refund case.',
                    'evidence' => ['No contradictory detail is present.'],
                  ],
                ],
                'prompt_name' => 'text-to-structured-evaluation.specialist',
                'prompt_version' => '1.0.0',
              ],
                summary: 'Text evaluation completed.',
            ),
            AudioToTextToEvaluationCoordinatorAgent::KEY => new OrchestrationResult(
                orchestrationId: 'orch-audio-001',
                status: OrchestrationResult::STATUS_COMPLETED,
                finalAgent: AudioToTextToEvaluationCoordinatorAgent::KEY,
                finalExecutionId: 'exec-audio-001',
                finalOutput: [
                'subject' => 'support call',
                'audio_reference' => 's3://bucket/audio/support-call.wav',
                'transcript' => 'Please refund the unused portion of my subscription.',
                'summary' => 'The transcript is clear and contains a direct refund request.',
                'recommended_action' => 'Escalate to billing review.',
                'confidence' => 0.89,
                'enabled_dimensions' => ['clarity', 'accuracy'],
                'dimensions' => [
                  'clarity' => [
                    'score' => 5,
                    'summary' => 'The caller clearly asks for a refund.',
                    'evidence' => ['The transcript contains a direct refund request.'],
                  ],
                  'accuracy' => [
                    'score' => 4,
                    'summary' => 'The request is internally consistent.',
                    'evidence' => ['The transcript does not contain conflicting information.'],
                  ],
                ],
                'transcription_prompt_name' => 'audio-to-text-to-evaluation.transcription',
                'transcription_prompt_version' => '1.0.0',
                'evaluation_prompt_name' => 'text-to-structured-evaluation.specialist',
                'evaluation_prompt_version' => '1.0.0',
              ],
                summary: 'Audio evaluation completed.',
            ),
            default => new OrchestrationResult(
                orchestrationId: 'orch-custom-001',
                status: OrchestrationResult::STATUS_COMPLETED,
                finalAgent: $request->entryAgent,
                finalExecutionId: 'exec-custom-001',
                finalOutput: ['task' => $request->task],
                summary: 'Custom orchestration completed.',
            ),
        };
    }
}
