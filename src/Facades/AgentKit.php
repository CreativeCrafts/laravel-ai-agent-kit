<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Facades;

use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluationResult;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluationResult;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluation;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluation;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\PromptBlueprint;
use Generator;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StreamChunk;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StreamComplete;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StreamFailure;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\EmbeddingsResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\EmbeddingsRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\ImageGenerationResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\ImageGenerationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\RerankingResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\RerankingRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\AudioGenerationResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\AudioGenerationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\LaravelAi\LaravelAiFilesService;
use CreativeCrafts\LaravelAiAgentKit\Core\LaravelAi\LaravelAiStoresService;
use CreativeCrafts\LaravelAiAgentKit\Support\AgentKitManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static TextToStructuredEvaluationResult evaluateText(TextToStructuredEvaluationRequest $request)
 * @method static AudioToTextToEvaluationResult evaluateAudio(AudioToTextToEvaluationRequest $request)
 * @method static \CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioImageStructuredEvaluationResult evaluateAudioImage(\CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioImageStructuredEvaluationRequest $request)
 * @method static OrchestrationResult orchestrate(OrchestrationRequest $request)
 * @method static TextToStructuredEvaluation textToStructuredEvaluation()
 * @method static AudioToTextToEvaluation audioToTextToEvaluation()
 * @method static AgentOrchestrator orchestrator()
 * @method static ExecutionResult run(PromptBlueprint $blueprint)
 * @method static Generator<int, StreamChunk|StreamComplete|StreamFailure> executeStream(ExecutionRequest $request)
 * @method static EmbeddingsResult embed(EmbeddingsRequest $request)
 * @method static TranscriptionResult transcribe(TranscriptionRequest $request)
 * @method static ImageGenerationResult generateImage(ImageGenerationRequest $request)
 * @method static RerankingResult rerank(RerankingRequest $request)
 * @method static AudioGenerationResult generateAudio(AudioGenerationRequest $request)
 * @method static LaravelAiFilesService laravelAiFiles()
 * @method static LaravelAiStoresService laravelAiStores()
 * @see AgentKitManager
 */
final class AgentKit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AgentKitManager::class;
    }
}
