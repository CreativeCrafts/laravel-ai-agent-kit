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
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\StreamingAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\AudioGenerationRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\EmbeddingsRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\ImageGenerationRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\RerankingRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\TranscriptionRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Core\LaravelAi\LaravelAiFilesService;
use CreativeCrafts\LaravelAiAgentKit\Core\LaravelAi\LaravelAiStoresService;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\AudioGenerationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\AudioGenerationResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\EmbeddingsRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\EmbeddingsResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\ImageGenerationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\ImageGenerationResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\RerankingRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\RerankingResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StreamChunk;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StreamComplete;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StreamFailure;
use Generator;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;

final readonly class AgentKitManager
{
    public function __construct(
      private TextToStructuredEvaluation $textEvaluation,
      private AudioToTextToEvaluation $audioEvaluation,
      private AgentOrchestrator $orchestrator,
      private BlueprintRunner $blueprintRunner,
      private Container $container,
    ) {}

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

    /**
     * @return Generator<int, StreamChunk|StreamComplete|StreamFailure>
     * @throws BindingResolutionException
     */
    public function executeStream(ExecutionRequest $request): Generator
    {
        return $this->container->make(StreamingAiRuntime::class)->executeStream($request);
    }

    /**
     * @throws BindingResolutionException
     */
    public function embed(EmbeddingsRequest $request): EmbeddingsResult
    {
        return $this->container->make(EmbeddingsRuntime::class)->embed($request);
    }

    /**
     * @throws BindingResolutionException
     */
    public function transcribe(TranscriptionRequest $request): TranscriptionResult
    {
        return $this->container->make(TranscriptionRuntime::class)->transcribe($request);
    }

    /**
     * @throws BindingResolutionException
     */
    public function generateImage(ImageGenerationRequest $request): ImageGenerationResult
    {
        return $this->container->make(ImageGenerationRuntime::class)->generate($request);
    }

    /**
     * @throws BindingResolutionException
     */
    public function rerank(RerankingRequest $request): RerankingResult
    {
        return $this->container->make(RerankingRuntime::class)->rerank($request);
    }

    /**
     * @throws BindingResolutionException
     */
    public function generateAudio(AudioGenerationRequest $request): AudioGenerationResult
    {
        return $this->container->make(AudioGenerationRuntime::class)->generate($request);
    }

    /**
     * @throws BindingResolutionException
     */
    public function laravelAiFiles(): LaravelAiFilesService
    {
        return $this->container->make(LaravelAiFilesService::class);
    }

    /**
     * @throws BindingResolutionException
     */
    public function laravelAiStores(): LaravelAiStoresService
    {
        return $this->container->make(LaravelAiStoresService::class);
    }
}
