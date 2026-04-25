<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Facades;

use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluation;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluationResult;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\PromptBlueprint;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluation;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluationResult;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Support\AgentKitManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static TextToStructuredEvaluationResult evaluateText(TextToStructuredEvaluationRequest $request)
 * @method static AudioToTextToEvaluationResult evaluateAudio(AudioToTextToEvaluationRequest $request)
 * @method static OrchestrationResult orchestrate(OrchestrationRequest $request)
 * @method static TextToStructuredEvaluation textToStructuredEvaluation()
 * @method static AudioToTextToEvaluation audioToTextToEvaluation()
 * @method static AgentOrchestrator orchestrator()
 * @method static ExecutionResult run(PromptBlueprint $blueprint)
 * @see AgentKitManager
 */
final class AgentKit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AgentKitManager::class;
    }
}
