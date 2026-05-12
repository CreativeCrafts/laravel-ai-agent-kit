<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\TextToStructuredEvaluationSpecialistAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Support\StructuredEvaluationOutputNormalizer;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Prompts\InMemoryPromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Prompts\PromptExecutionMapper;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAiRuntime;
use Laravel\Ai\ObjectSchema;

it('passes caller provided object schemas to the evaluation runtime request', function (): void {
    $schema = new ObjectSchema([], name: 'support_call_schema');

    $requests = runSchemaDrivenAudioEvaluationSpecialist($schema);

    expect($requests)
      ->toHaveCount(1)
      ->and($requests[0])->toBeInstanceOf(ExecutionRequest::class)
      ->and($requests[0]->schema)->toBe($schema);
});

it('passes caller provided closure schemas to the evaluation runtime request', function (): void {
    $schema = fn (): array => ['type' => 'object'];

    $requests = runSchemaDrivenAudioEvaluationSpecialist($schema);

    expect($requests)
      ->toHaveCount(1)
      ->and($requests[0])->toBeInstanceOf(ExecutionRequest::class)
      ->and($requests[0]->schema)->toBe($schema);
});

it('passes caller provided class-string schemas to the evaluation runtime request', function (): void {
    $schema = TextToStructuredEvaluationSpecialistAgent::class;

    $requests = runSchemaDrivenAudioEvaluationSpecialist($schema);

    expect($requests)
      ->toHaveCount(1)
      ->and($requests[0])->toBeInstanceOf(ExecutionRequest::class)
      ->and($requests[0]->schema)->toBe($schema);
});

it('returns raw structured output for custom audio evaluation schemas', function (): void {
    $schema = new ObjectSchema([], name: 'support_call_schema');
    $fakeRuntime = schemaDrivenAudioEvaluationRuntime();
    $agent = schemaDrivenAudioEvaluationSpecialist($fakeRuntime);

    $result = $agent->handle(schemaDrivenAudioEvaluationContext($schema));

    expect($fakeRuntime->requests())
      ->toHaveCount(1)
      ->and($result->output['structured_output'])->toBe([
        'resolved' => true,
        'risk_level' => 'low',
        'confidence' => 0.88,
      ])
      ->and($result->output['dimensions']['custom_schema']['summary'])->toBe('Custom schema structured output was returned.')
      ->and($result->output['usage']['completion_tokens'])->toBe(11);
});

/**
 * @return list<ExecutionRequest>
 */
function runSchemaDrivenAudioEvaluationSpecialist(mixed $schema): array
{
    $fakeRuntime = schemaDrivenAudioEvaluationRuntime();
    $agent = schemaDrivenAudioEvaluationSpecialist($fakeRuntime);

    $agent->handle(schemaDrivenAudioEvaluationContext($schema));

    return $fakeRuntime->requests();
}

function schemaDrivenAudioEvaluationRuntime(): FakeAiRuntime
{
    return new FakeAiRuntime([
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
}

function schemaDrivenAudioEvaluationSpecialist(FakeAiRuntime $fakeRuntime): TextToStructuredEvaluationSpecialistAgent
{
    $promptRepository = new InMemoryPromptRepository([
      'text-to-structured-evaluation.specialist' => [
        '1.0.0' => 'Evaluate {{ subject }} using {{ enabled_dimensions }}. Text: {{ text }}',
      ],
    ]);

    return new TextToStructuredEvaluationSpecialistAgent(
        providerRegistry: new class () implements ProviderRegistry {
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
}

function schemaDrivenAudioEvaluationContext(mixed $schema): AgentExecutionContext
{
    return new AgentExecutionContext(
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
    );
}
