<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Support;

use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluation;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluationResult;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\PromptBlueprint;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluation;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluationResult;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\BlueprintRunner;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;

final readonly class AgentKitManager
{
    public function __construct(
        private TextToStructuredEvaluation $textEvaluation,
        private AudioToTextToEvaluation $audioEvaluation,
        private AgentOrchestrator $orchestrator,
        private BlueprintRunner $blueprintRunner,
    ) {
    }

    public function evaluateText(TextToStructuredEvaluationRequest $request): TextToStructuredEvaluationResult
    {
        return $this->textEvaluation->evaluate($request);
    }

    public function evaluateAudio(AudioToTextToEvaluationRequest $request): AudioToTextToEvaluationResult
    {
        return $this->audioEvaluation->evaluate($request);
    }

    public function orchestrate(OrchestrationRequest $request): OrchestrationResult
    {
        return $this->orchestrator->run($request);
    }

    public function run(PromptBlueprint $blueprint): ExecutionResult
    {
        return $this->blueprintRunner->run($blueprint);
    }

    public function textToStructuredEvaluation(): TextToStructuredEvaluation
    {
        return $this->textEvaluation;
    }

    public function audioToTextToEvaluation(): AudioToTextToEvaluation
    {
        return $this->audioEvaluation;
    }

    public function orchestrator(): AgentOrchestrator
    {
        return $this->orchestrator;
    }
}
