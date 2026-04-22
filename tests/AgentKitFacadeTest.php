<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\AudioToTextToEvaluationCoordinatorAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\TextToStructuredEvaluationCoordinatorAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluation;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluationResult;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluation;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluationResult;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationResult;
use CreativeCrafts\LaravelAiAgentKit\Facades\AgentKit;
use CreativeCrafts\LaravelAiAgentKit\Support\AgentKitManager;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fakes\AgentKitTestingAgentRegistry;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fakes\AgentKitTestingOrchestrator;
use Illuminate\Support\Facades\Facade;

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
