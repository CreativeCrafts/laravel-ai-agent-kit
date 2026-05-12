<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\TextToStructuredEvaluationSpecialistAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Exceptions\TextToStructuredEvaluationException;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Support\StructuredEvaluationOutputNormalizer;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Prompts\InMemoryPromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Prompts\PromptExecutionMapper;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAiRuntime;
use Laravel\Ai\ObjectSchema;

it('rejects invalid audio evaluation schema class strings', function (): void {
    new AudioToTextToEvaluationRequest(
        subject: 'support call',
        audioReference: 's3://bucket/audio/support-call.wav',
        enabledDimensions: ['custom_schema'],
        schema: 'Missing\\AudioEvaluationSchema',
    );
})->throws(InvalidArgumentException::class, 'AudioToTextToEvaluation request schema class-string [Missing\\AudioEvaluationSchema] does not exist.');

it('fails the evaluation stage when a custom schema produces no structured output', function (): void {
    $promptRepository = new InMemoryPromptRepository([
        'text-to-structured-evaluation.specialist' => [
            '1.0.0' => 'Evaluate {{ subject }} using {{ enabled_dimensions }}. Text: {{ text }}',
        ],
    ]);
    $fakeRuntime = new FakeAiRuntime([
        new ExecutionResult(
            runId: 'schema-driven-empty-output-001',
            output: '',
            provider: 'openai',
            model: 'gpt-4o-mini',
            structuredOutput: [],
        ),
    ]);
    $agent = new TextToStructuredEvaluationSpecialistAgent(
        providerRegistry: new SchemaDrivenFailureProviderRegistry(),
        promptRepository: $promptRepository,
        promptExecutionMapper: new PromptExecutionMapper($promptRepository),
        aiRuntime: $fakeRuntime,
        structuredEvaluationOutputNormalizer: new StructuredEvaluationOutputNormalizer(),
    );

    $agent->handle(
        new AgentExecutionContext(
            orchestrationId: 'orch-schema-driven-failure-001',
            executionId: 'schema-driven-empty-output-001',
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
                'evaluation_schema' => new ObjectSchema([], name: 'support_call_schema'),
                'custom_evaluation_schema' => true,
            ],
        ),
    );
})->throws(
    TextToStructuredEvaluationException::class,
    'evaluation stage expected non-empty structured output for the custom audio evaluation schema.',
);

final class SchemaDrivenFailureProviderRegistry implements ProviderRegistry
{
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
}
