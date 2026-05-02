<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Facades;

use CreativeCrafts\LaravelAiAgentKit\Support\AgentKitManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluationResult evaluateText(\CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluationRequest $request)
 * @method static \CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluationResult evaluateAudio(\CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluationRequest $request)
 * @method static \CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationResult orchestrate(\CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest $request)
 * @method static \CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluation textToStructuredEvaluation()
 * @method static \CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluation audioToTextToEvaluation()
 * @method static \CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator orchestrator()
 * @method static \CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult run(\CreativeCrafts\LaravelAiAgentKit\Blueprints\PromptBlueprint $blueprint)
 * @method static \Generator<int, \CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StreamChunk|\CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StreamComplete|\CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StreamFailure> executeStream(\CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest $request)
 * @method static \CreativeCrafts\LaravelAiAgentKit\Core\Modality\EmbeddingsResult embed(\CreativeCrafts\LaravelAiAgentKit\Core\Modality\EmbeddingsRequest $request)
 * @method static \CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionResult transcribe(\CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionRequest $request)
 * @method static \CreativeCrafts\LaravelAiAgentKit\Core\Modality\ImageGenerationResult generateImage(\CreativeCrafts\LaravelAiAgentKit\Core\Modality\ImageGenerationRequest $request)
 * @method static \CreativeCrafts\LaravelAiAgentKit\Core\Modality\RerankingResult rerank(\CreativeCrafts\LaravelAiAgentKit\Core\Modality\RerankingRequest $request)
 * @method static \CreativeCrafts\LaravelAiAgentKit\Core\Modality\AudioGenerationResult generateAudio(\CreativeCrafts\LaravelAiAgentKit\Core\Modality\AudioGenerationRequest $request)
 * @method static \CreativeCrafts\LaravelAiAgentKit\Core\LaravelAi\LaravelAiFilesService laravelAiFiles()
 * @method static \CreativeCrafts\LaravelAiAgentKit\Core\LaravelAi\LaravelAiStoresService laravelAiStores()
 * @see AgentKitManager
 */
final class AgentKit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AgentKitManager::class;
    }
}
