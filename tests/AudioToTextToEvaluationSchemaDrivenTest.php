<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\TextToStructuredEvaluationSpecialistAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluation;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Support\StructuredEvaluationOutputNormalizer;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\AgentRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Prompts\InMemoryPromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Prompts\PromptExecutionMapper;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAiRuntime;
use Laravel\Ai\ObjectSchema;
use RuntimeException;

$assertAudioEvaluationSchemaIsPassedThrough = function (mixed $schema): void {
    $orchestrator = new class([
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
    ]) implements AgentOrchestrator {
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
                finalAgent: 'audio-to-text-to-evaluation.coordinator',
                finalExecutionId: 'exec-schema-driven-final',
                finalOutput: $this->finalOutput,
                summary: 'Schema-driven audio evaluation completed.',
            );
        }
    };

    $blueprint = new AudioToTextToEvaluation(
        agentOrchestrator: $orchestrator,
        agentRegistry: new class implements AgentRegistry {
            /** @param class-string<Agent> $agentClass */
            public function register(string $agentClass): void
            {
            }

            /** @param iterable<class-string<Agent>> $agentClasses */
            public function registerMany(iterable $agentClasses): void
            {
            }

            public function has(string $agentKey): bool
            {
                return true;
            }

            public function get(string $agentKey): Agent
            {
                throw new RuntimeException('Schema-driven no-op registry does not resolve agents.');
            }

            /** @return array<string, Agent> */
            public function all(): array
            {
                return [];
            }
        },
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

    expect($orchestrator->requests)
        ->toHaveCount(1)
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
};

it('passes caller provided object schemas through the audio evaluation orchestration request', function () use ($assertAudioEvaluationSchemaIsPassedThrough): void {
    $assertAudioEvaluationSchemaIsPassedThrough(new ObjectSchema([], name: 'support_call_schema'));
});

it('passes caller provided closure schemas through the audio evaluation orchestration request', function () use ($assertAudioEvaluationSchemaIsPassedThrough): void {
    $assertAudioEvaluationSchemaIsPassedThrough(fn (): array => ['type' => 'object']);
});

it('passes caller provided class-string schemas through the audio evaluation orchestration request', function () use ($assertAudioEvaluationSchemaIsPassedThrough): void {
    $assertAudioEvaluationSchemaIsPassedThrough(TextToStructuredEvaluationSpecialistAgent::class);
});

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
        providerRegistry: new class implements ProviderRegistry {
            public function has(string $providerName): bool
            {
                return $providerName === 'openai';
            }

            public function get(string $providerName): ProviderDefinition
            {
                if ($providerName !== 'openai') {
                    throw new RuntimeException('Unknown provider.');
                }

                return new ProviderDefinition(
                    name: 'openai',
                    driver: 'openai',
                    enabled: true,
                    capabilities: ['text_generation', 'structured_output'],
                );
            }

            /** @return array<string, ProviderDefinition> */
            public function all(): array
            {
                return [
                    'openai' => $this->get('openai'),
                ];
            }
        },
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

    expect($requests)
        ->toHaveCount(1)
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
