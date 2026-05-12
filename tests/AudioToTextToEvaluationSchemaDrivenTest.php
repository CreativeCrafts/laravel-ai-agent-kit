<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\TextToStructuredEvaluationSpecialistAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluation;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Support\StructuredEvaluationOutputNormalizer;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Prompts\InMemoryPromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Prompts\PromptExecutionMapper;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fakes\AudioEvaluation\RecordingSchemaDrivenAudioOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fakes\AudioEvaluation\SchemaDrivenAudioEvaluationSchema;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fakes\AudioEvaluation\SchemaDrivenNoopAgentRegistry;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fakes\AudioEvaluation\SchemaDrivenProviderRegistry;
use Laravel\Ai\ObjectSchema;

it('passes caller provided schemas through the audio evaluation orchestration request', function (mixed $schema): void {
    $orchestrator = new RecordingSchemaDrivenAudioOrchestrator([
        'subject' => 'support call',
        'audio_reference' => 's3://bucket/audio/support-call.wav',
        'transcript' => 'The customer says the issue is resolved.',
        'summary' => 'Resolved support call.',
        'recommended_action' => 'Close the ticket.',
        'confidence' => 0.92,
        'enabled_dimensions' => ['custom_schema'],
        'dimensions' => [
            'custom_schema' => [
                'score' => 1,
                'summary' => 'Custom structured output was returned.',
                'evidence' => ['structured_output'],
            ],
        ],
        'structured_output' => [
            'resolved' => true,
            'risk_level' => 'low',
        ],
        'segments' => [
            [
                'text' => 'The customer says the issue is resolved.',
                'speaker' => 'speaker_0',
                'start_seconds' => 0.0,
                'end_seconds' => 2.5,
            ],
        ],
        'metadata' => ['custom_evaluation_schema' => true],
        'transcription_provider' => 'openai',
        'transcription_model' => 'gpt-4o-transcribe-diarize',
        'evaluation_provider' => 'openai',
        'evaluation_model' => 'gpt-4o-mini',
        'usage' => [
            'transcription' => ['prompt_tokens' => 3],
            'evaluation' => ['completion_tokens' => 9],
        ],
        'transcription_prompt_name' => 'audio-to-text-to-evaluation.transcription',
        'transcription_prompt_version' => '1.0.0',
        'evaluation_prompt_name' => 'text-to-structured-evaluation.specialist',
        'evaluation_prompt_version' => '1.0.0',
    ]);

    $blueprint = new AudioToTextToEvaluation(
        agentOrchestrator: $orchestrator,
        agentRegistry: new SchemaDrivenNoopAgentRegistry(),
    );

    $result = $blueprint->evaluate(
        new AudioToTextToEvaluationRequest(
            subject: 'support call',
            audioReference: 's3://bucket/audio/support-call.wav',
            audioMimeType: 'audio/wav',
            enabledDimensions: ['custom_schema'],
            transcriptionPromptVersion: '1.0.0',
            evaluationPromptVersion: '1.0.0',
            schema: $schema,
        ),
    );

    expect($orchestrator->requests)->toHaveCount(1)
        ->and($orchestrator->requests[0]->input['evaluation_schema'])->toBe($schema)
        ->and($orchestrator->requests[0]->input['custom_evaluation_schema'])->toBeTrue()
        ->and($result->structuredOutput)->toBe([
            'resolved' => true,
            'risk_level' => 'low',
        ])
        ->and($result->segments[0]['speaker'])->toBe('speaker_0')
        ->and($result->transcriptionProvider)->toBe('openai')
        ->and($result->evaluationModel)->toBe('gpt-4o-mini')
        ->and($result->usage['evaluation']['completion_tokens'])->toBe(9)
        ->and($result->toArray()['structured_output']['resolved'])->toBeTrue();
})->with([
    'object schema' => [new ObjectSchema([], name: 'support_call_schema')],
    'closure schema' => [fn (): array => ['type' => 'object']],
    'class-string schema' => [SchemaDrivenAudioEvaluationSchema::class],
]);

it('sends custom audio evaluation schemas to the runtime and returns raw structured output', function (): void {
    $promptRepository = new InMemoryPromptRepository([
        'text-to-structured-evaluation.specialist' => [
            '1.0.0' => 'Evaluate {{ subject }} using {{ enabled_dimensions }}. Text: {{ text }}',
        ],
    ]);
    $fakeRuntime = new FakeAiRuntime([
        new ExecutionResult(
            runId: 'schema-driven-evaluation-001',
            output: '',
            provider: 'openai',
            model: 'gpt-4o-mini',
            usage: ['completion_tokens' => 11],
            structuredOutput: [
                'resolved' => true,
                'risk_level' => 'low',
                'confidence' => 0.88,
            ],
        ),
    ]);
    $schema = new ObjectSchema([], name: 'support_call_schema');
    $agent = new TextToStructuredEvaluationSpecialistAgent(
        providerRegistry: new SchemaDrivenProviderRegistry(),
        promptRepository: $promptRepository,
        promptExecutionMapper: new PromptExecutionMapper($promptRepository),
        aiRuntime: $fakeRuntime,
        structuredEvaluationOutputNormalizer: new StructuredEvaluationOutputNormalizer(),
    );

    $result = $agent->handle(
        new AgentExecutionContext(
            orchestrationId: 'orch-schema-driven-001',
            executionId: 'schema-driven-evaluation-001',
            parentExecutionId: null,
            agent: new AgentDefinition(
                key: TextToStructuredEvaluationSpecialistAgent::KEY,
                displayName: 'Specialist',
                requiredCapabilities: ['text_generation', 'structured_output'],
                primaryProviderProfile: 'openai',
            ),
            providerProfile: 'openai',
            task: 'Evaluate transcript.',
            payload: [
                'subject' => 'support call',
                'text' => 'The issue is resolved.',
                'enabled_dimensions' => ['custom_schema'],
                'prompt_name' => 'text-to-structured-evaluation.specialist',
                'prompt_version' => '1.0.0',
                'prompt_variables' => [],
                'evaluation_schema' => $schema,
                'custom_evaluation_schema' => true,
            ],
        ),
    );

    $requests = $fakeRuntime->requests();

    expect($requests)->toHaveCount(1)
        ->and($requests[0])->toBeInstanceOf(ExecutionRequest::class)
        ->and($requests[0]->schema)->toBe($schema)
        ->and($result->output['structured_output'])->toBe([
            'resolved' => true,
            'risk_level' => 'low',
            'confidence' => 0.88,
        ])
        ->and($result->output['dimensions']['custom_schema']['summary'])->toBe('Custom schema structured output was returned.')
        ->and($result->output['usage']['completion_tokens'])->toBe(11);
});
