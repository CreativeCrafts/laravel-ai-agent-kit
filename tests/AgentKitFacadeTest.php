<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\AudioToTextToEvaluationCoordinatorAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\TextToStructuredEvaluationCoordinatorAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluation;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluationResult;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\PromptBlueprint;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluation;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluationResult;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\BlueprintRunner;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\StreamingAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\EmbeddingsRuntime;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\EmbeddingsRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StreamComplete;
use CreativeCrafts\LaravelAiAgentKit\Facades\AgentKit;
use CreativeCrafts\LaravelAiAgentKit\Support\AgentKitManager;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fakes\AgentKitTestingAgentRegistry;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fakes\AgentKitTestingOrchestrator;
use Generator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Facade;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\Embeddings;

beforeEach(function (): void {
    refreshAgentKitFacadeBinding();
});

it('resolves the top-level manager and facade from the container', function (): void {
    $orchestrator = configureAgentKitFacadeTestBindings();

    $manager = app(AgentKitManager::class);

    expect($manager)
      ->toBeInstanceOf(AgentKitManager::class)
      ->and($manager->textToStructuredEvaluation())->toBeInstanceOf(TextToStructuredEvaluation::class)
      ->and($manager->audioToTextToEvaluation())->toBeInstanceOf(AudioToTextToEvaluation::class)
      ->and($manager->orchestrator())->toBe($orchestrator)
      ->and(AgentKit::getFacadeRoot())->toBe($manager);

    /** @var BlueprintRunner $resolvedBlueprintRunner */
    $resolvedBlueprintRunner = app(BlueprintRunner::class);
    expect($resolvedBlueprintRunner)->toBeInstanceOf(BlueprintRunner::class);
});

it('delegates run() through the facade to BlueprintRunner exactly once', function (): void {
    configureAgentKitFacadeTestBindings();

    $expected = new ExecutionResult(
        runId: 'run-facade-001',
        output: 'Manager pass-through.',
    );

    $blueprintRunner = new class ($expected) implements BlueprintRunner {
        public int $invocations = 0;

        /** @var list<PromptBlueprint> */
        public array $receivedBlueprints = [];

        public function __construct(public readonly ExecutionResult $expected)
        {
        }

        public function run(PromptBlueprint $blueprint): ExecutionResult
        {
            $this->invocations++;
            $this->receivedBlueprints[] = $blueprint;

            return $this->expected;
        }
    };

    app()->instance(BlueprintRunner::class, $blueprintRunner);
    app()->forgetInstance(AgentKitManager::class);
    Facade::clearResolvedInstance(AgentKitManager::class);

    $blueprint = PromptBlueprint::forPrompt('package.test')->withRunId('run-facade-001');

    $result = AgentKit::run($blueprint);

    expect($blueprintRunner->invocations)->toBe(1)
      ->and($blueprintRunner->receivedBlueprints[0])->toBe($blueprint)
      ->and($result)->toBe($expected);
});

it('delegates text evaluation through the facade to the existing blueprint workflow', function (): void {
    $orchestrator = configureAgentKitFacadeTestBindings();

    $result = AgentKit::evaluateText(
        new TextToStructuredEvaluationRequest(
            subject: 'support reply',
            text: 'Please refund the unused portion of my subscription.',
            enabledDimensions: ['clarity', 'accuracy'],
            promptVersion: '1.0.0',
        ),
    );

    expect($result)
      ->toBeInstanceOf(TextToStructuredEvaluationResult::class)
      ->and($result->summary)->toBe('The request is clear and directly asks for a refund.')
      ->and($result->recommendedAction)->toBe('Route the request to the refund review workflow.')
      ->and($result->confidence)->toBe(0.95)
      ->and($result->promptName)->toBe('text-to-structured-evaluation.specialist')
      ->and($orchestrator->requests)->toHaveCount(1)
      ->and($orchestrator->requests[0]->entryAgent)->toBe(TextToStructuredEvaluationCoordinatorAgent::KEY);
});

it('delegates audio evaluation through the facade to the existing staged blueprint workflow', function (): void {
    $orchestrator = configureAgentKitFacadeTestBindings();

    $result = AgentKit::evaluateAudio(
        new AudioToTextToEvaluationRequest(
            subject: 'support call',
            audioReference: 's3://bucket/audio/support-call.wav',
            audioMimeType: 'audio/wav',
            enabledDimensions: ['clarity', 'accuracy'],
            transcriptionPromptVersion: '1.0.0',
            evaluationPromptVersion: '1.0.0',
        ),
    );

    expect($result)
      ->toBeInstanceOf(AudioToTextToEvaluationResult::class)
      ->and($result->audioReference)->toBe('s3://bucket/audio/support-call.wav')
      ->and($result->transcript)->toBe('Please refund the unused portion of my subscription.')
      ->and($result->summary)->toBe('The transcript is clear and contains a direct refund request.')
      ->and($result->recommendedAction)->toBe('Escalate to billing review.')
      ->and($result->transcriptionPromptName)->toBe('audio-to-text-to-evaluation.transcription')
      ->and($result->evaluationPromptName)->toBe('text-to-structured-evaluation.specialist')
      ->and($orchestrator->requests)->toHaveCount(1)
      ->and($orchestrator->requests[0]->entryAgent)->toBe(AudioToTextToEvaluationCoordinatorAgent::KEY);
});

it('delegates orchestration requests through the facade without reshaping the package-owned result', function (): void {
    $orchestrator = configureAgentKitFacadeTestBindings();

    $result = AgentKit::orchestrate(
        new OrchestrationRequest(
            entryAgent: 'support.agent',
            task: 'Draft a short refund response.',
            input: ['subscription_id' => 'sub-123'],
        ),
    );

    expect($result)
      ->toBeInstanceOf(OrchestrationResult::class)
      ->and($result->finalAgent)->toBe('support.agent')
      ->and($result->summary)->toBe('Custom orchestration completed.')
      ->and($result->finalOutput)->toBe(['task' => 'Draft a short refund response.'])
      ->and($orchestrator->requests)->toHaveCount(1)
      ->and($orchestrator->requests[0]->task)->toBe('Draft a short refund response.');
});

it('delegates executeStream through the facade to the same StreamingAiRuntime binding as the container', function (): void {
    app()->register(AiServiceProvider::class);

    /** @var array<string, mixed> $ai */
    $ai = require __DIR__.'/../vendor/laravel/ai/config/ai.php';
    Config::set('ai', $ai);
    Config::set('ai.default', 'openai');
    Config::set('ai.providers', [
        'openai' => [
            'driver' => 'openai',
            'key' => 'test-key-for-ci',
        ],
    ]);

    configureAgentKitFacadeTestBindings();

    $streaming = new class () implements StreamingAiRuntime {
        public int $executeStreamCalls = 0;

        public function executeStream(ExecutionRequest $request): Generator
        {
            $this->executeStreamCalls++;

            yield new StreamComplete(
                runId: $request->runId,
                output: 'facade-stream',
                provider: 'openai',
                model: 'gpt-4o-mini',
                usage: ['prompt_tokens' => 1, 'completion_tokens' => 2],
            );
        }
    };

    app()->instance(StreamingAiRuntime::class, $streaming);
    refreshAgentKitFacadeBinding();

    $events = iterator_to_array(AgentKit::executeStream(
        new ExecutionRequest(
            runId: 'run-facade-stream',
            prompt: 'Hello.',
            provider: 'openai',
            model: 'gpt-4o-mini',
        ),
    ));

    expect($streaming->executeStreamCalls)->toBe(1)
        ->and($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(StreamComplete::class)
        ->and($events[0]->output)->toBe('facade-stream')
        ->and(app(StreamingAiRuntime::class))->toBe($streaming);
});

it('delegates embed through AgentKitManager using the same EmbeddingsRuntime singleton as app()', function (): void {
    app()->register(AiServiceProvider::class);

    /** @var array<string, mixed> $ai */
    $ai = require __DIR__.'/../vendor/laravel/ai/config/ai.php';
    Config::set('ai', $ai);
    Config::set('ai.default', 'openai');
    Config::set('ai.default_for_embeddings', 'openai');
    Config::set('ai.providers', [
        'openai' => [
            'driver' => 'openai',
            'key' => 'test-key-for-ci',
        ],
    ]);

    Embeddings::fake([[[0.1, 0.9]]])->preventStrayEmbeddings();

    configureAgentKitFacadeTestBindings();

    app()->forgetInstance(EmbeddingsRuntime::class);
    refreshAgentKitFacadeBinding();

    $resolved = app(EmbeddingsRuntime::class);
    $manager = app(AgentKitManager::class);

    expect($manager)->toBe(AgentKit::getFacadeRoot())
        ->and(spl_object_id($resolved))->toBe(spl_object_id(app(EmbeddingsRuntime::class)));

    $result = $manager->embed(
        new EmbeddingsRequest(
            runId: 'run-facade-emb',
            inputs: ['one'],
            dimensions: 2,
            provider: 'openai',
            model: 'text-embedding-3-small',
        ),
    );

    expect($result->vectors[0][1])->toBe(0.9);
});

function configureAgentKitFacadeTestBindings(): AgentKitTestingOrchestrator
{
    $orchestrator = new AgentKitTestingOrchestrator();
    $registry = new AgentKitTestingAgentRegistry();

    app()->instance(AgentOrchestrator::class, $orchestrator);
    app()->instance(TextToStructuredEvaluation::class, new TextToStructuredEvaluation($orchestrator, $registry));
    app()->instance(AudioToTextToEvaluation::class, new AudioToTextToEvaluation($orchestrator, $registry));

    refreshAgentKitFacadeBinding();

    return $orchestrator;
}

function refreshAgentKitFacadeBinding(): void
{
    app()->forgetInstance(AgentKitManager::class);
    Facade::clearResolvedInstance(AgentKitManager::class);
}
