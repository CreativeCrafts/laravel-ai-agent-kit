<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\TextToStructuredEvaluationCoordinatorAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\TextToStructuredEvaluationSpecialistAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Exceptions\TextToStructuredEvaluationException;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluation;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\AgentRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Prompts\PromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\AgentProviderProfileSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\ContainerAgentRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\SynchronousAgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredAgentProviderProfileSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\DefaultProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Prompts\InMemoryPromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Prompts\PromptExecutionMapper;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAiRuntime;

beforeEach(function (): void {
    config()->set('ai-agent-kit.providers', [
      'openai-default' => [
        'driver' => 'openai',
        'enabled' => true,
        'capabilities' => ['text_generation'],
        'options' => [],
      ],
      'openai-structured' => [
        'driver' => 'openai',
        'enabled' => true,
        'capabilities' => ['structured_output'],
        'options' => [],
      ],
    ]);
    config()->set('ai-agent-kit.default_provider', 'openai-default');
    config()->set('ai-agent-kit.failover_order', ['openai-default', 'openai-structured']);

    refreshTextToStructuredEvaluationBindings();

    $promptRepository = new InMemoryPromptRepository([
      'text-to-structured-evaluation.specialist' => [
        '1.0.0' => <<<PROMPT
                       Evaluate the following text for {{subject}}.
                       Enabled dimensions: {{enabled_dimensions}}
                       Text: {{text}}
                       PROMPT,
      ],
    ]);

    app()->instance(InMemoryPromptRepository::class, $promptRepository);
    app()->instance(PromptRepository::class, $promptRepository);

    app()->forgetInstance(PromptExecutionMapper::class);

    $agentRegistry = app(AgentRegistry::class);
    $agentRegistry->registerMany([
      TextToStructuredEvaluationCoordinatorAgent::class,
      TextToStructuredEvaluationSpecialistAgent::class,
    ]);
});

function refreshTextToStructuredEvaluationBindings(): void
{
    app()->forgetInstance(ConfiguredProviderRegistry::class);
    app()->forgetInstance(ProviderRegistry::class);
    app()->forgetInstance(DefaultProviderSelector::class);
    app()->forgetInstance(ProviderSelector::class);
    app()->forgetInstance(ConfiguredAgentProviderProfileSelector::class);
    app()->forgetInstance(AgentProviderProfileSelector::class);
    app()->forgetInstance(SynchronousAgentOrchestrator::class);
    app()->forgetInstance(AgentOrchestrator::class);
    app()->forgetInstance(PromptExecutionMapper::class);
    app()->forgetInstance(AiRuntime::class);
    app()->forgetInstance(ContainerAgentRegistry::class);
    app()->forgetInstance(AgentRegistry::class);
}

it('returns one final structured result from a single blueprint call over the orchestrator', function () {
    $fakeRuntime = new FakeAiRuntime([
      new ExecutionResult(
          runId: 'runtime-run-001',
          output: json_encode([
          'summary' => 'The text is clear and action-oriented.',
          'recommended_action' => 'Approve the response with minor edits.',
          'confidence' => 0.92,
          'dimensions' => [
            'clarity' => [
              'score' => 5,
              'summary' => 'The core message is easy to follow.',
              'evidence' => ['Direct request is stated in the opening sentence.'],
            ],
            'accuracy' => [
              'score' => 4,
              'summary' => 'Claims are mostly supported by the provided text.',
              'evidence' => ['No unsupported product promises were detected.'],
            ],
          ],
        ], JSON_THROW_ON_ERROR),
          provider: 'openai-structured',
          model: 'gpt-test-structured',
      ),
    ]);

    app()->instance(AiRuntime::class, $fakeRuntime);

    $result = app(TextToStructuredEvaluation::class)->evaluate(
        new TextToStructuredEvaluationRequest(
            subject: 'customer support reply',
            text: 'We can refund the unused portion of your subscription within five business days.',
            enabledDimensions: ['clarity', 'accuracy'],
            promptVersion: '1.0.0',
        ),
    );

    expect($result->subject)
      ->toBe('customer support reply')
      ->and($result->summary)->toBe('The text is clear and action-oriented.')
      ->and($result->recommendedAction)->toBe('Approve the response with minor edits.')
      ->and($result->confidence)->toBe(0.92)
      ->and($result->enabledDimensions)->toBe(['clarity', 'accuracy'])
      ->and($result->dimension('clarity')?->score)->toBe(5)
      ->and($result->dimension('accuracy')?->summary)->toBe('Claims are mostly supported by the provided text.')
      ->and($result->finalAgent)->toBe(TextToStructuredEvaluationCoordinatorAgent::KEY)
      ->and($result->promptName)->toBe('text-to-structured-evaluation.specialist')
      ->and($result->promptVersion)->toBe('1.0.0')
      ->and($result->trace)->toHaveCount(3)
      ->and($result->trace[0]->agentKey)->toBe(TextToStructuredEvaluationCoordinatorAgent::KEY)
      ->and($result->trace[0]->resultKind)->toBe('delegate')
      ->and($result->trace[0]->targetAgent)->toBe(TextToStructuredEvaluationSpecialistAgent::KEY)
      ->and($result->trace[1]->agentKey)->toBe(TextToStructuredEvaluationSpecialistAgent::KEY)
      ->and($result->trace[2]->agentKey)->toBe(TextToStructuredEvaluationCoordinatorAgent::KEY)
      ->and($fakeRuntime)->toHaveRuntimeExecutions(1);

    $runtimeRequest = $fakeRuntime->lastRequest();

    expect($runtimeRequest)
      ->toBeInstanceOf(ExecutionRequest::class)
      ->and($runtimeRequest?->provider)->toBe('openai-structured')
      ->and($runtimeRequest?->prompt)->toContain('Evaluate the following text for customer support reply.')
      ->and($runtimeRequest?->prompt)->toContain('Enabled dimensions: clarity, accuracy')
      ->and($runtimeRequest?->metadata['prompt_name'])->toBe('text-to-structured-evaluation.specialist')
      ->and($runtimeRequest?->metadata['prompt_version'])->toBe('1.0.0');
});

it('keeps the result schema fixed while allowing callers to enable a subset of dimensions', function () {
    $fakeRuntime = new FakeAiRuntime([
      new ExecutionResult(
          runId: 'runtime-run-002',
          output: json_encode([
          'summary' => 'The text is concise but misses supporting context.',
          'recommended_action' => 'Request one factual citation before publishing.',
          'confidence' => 0.81,
          'dimensions' => [
            'completeness' => [
              'score' => 2,
              'summary' => 'The text omits key implementation detail.',
              'evidence' => ['No source or policy reference is included.'],
            ],
          ],
        ], JSON_THROW_ON_ERROR),
          provider: 'openai-structured',
          model: 'gpt-test-structured',
      ),
    ]);

    app()->instance(AiRuntime::class, $fakeRuntime);

    $result = app(TextToStructuredEvaluation::class)->evaluate(
        new TextToStructuredEvaluationRequest(
            subject: 'release note',
            text: 'This feature improves reliability.',
            enabledDimensions: ['completeness'],
            promptVersion: '1.0.0',
            promptVariables: ['custom_hint' => 'Focus on implementation detail.'],
        ),
    );

    expect(array_keys($result->dimensions))
      ->toBe(['completeness'])
      ->and($result->enabledDimensions)->toBe(['completeness'])
      ->and($result->toArray()['dimensions']['completeness']['score'])->toBe(2)
      ->and($fakeRuntime->lastRequest()?->prompt)->toContain('Enabled dimensions: completeness');
});

it('repairs wrapped structured output returned by the specialist runtime response', function () {
    $fakeRuntime = new FakeAiRuntime([
      new ExecutionResult(
          runId: 'runtime-run-002b',
          output: <<<OUTPUT
            Here is the structured evaluation you requested:
            
            ```json
            {
              "summary": "The response is specific and easy to action.",
              "recommended_action": "Send the response as drafted.",
              "confidence": 0.88,
              "dimensions": {
                "clarity": {
                  "score": 5,
                  "summary": "The wording is direct and unambiguous.",
                  "evidence": ["The next step is stated clearly in the first sentence."]
                }
              }
            }
            ```
            OUTPUT,
          provider: 'openai-structured',
          model: 'gpt-test-structured',
      ),
    ]);

    app()->instance(AiRuntime::class, $fakeRuntime);

    $result = app(TextToStructuredEvaluation::class)->evaluate(
        new TextToStructuredEvaluationRequest(
            subject: 'repairable response',
            text: 'Please confirm whether the refund can be processed today.',
            enabledDimensions: ['clarity'],
            promptVersion: '1.0.0',
        ),
    );

    expect($result->summary)
      ->toBe('The response is specific and easy to action.')
      ->and($result->recommendedAction)->toBe('Send the response as drafted.')
      ->and($result->confidence)->toBe(0.88)
      ->and($result->dimension('clarity')?->score)->toBe(5);
});

it('throws a typed exception when the specialist refuses to return structured output', function () {
    $fakeRuntime = new FakeAiRuntime([
      new ExecutionResult(
          runId: 'runtime-run-002c',
          output: 'I cannot provide the requested JSON response for this evaluation.',
          provider: 'openai-structured',
          model: 'gpt-test-structured',
      ),
    ]);

    app()->instance(AiRuntime::class, $fakeRuntime);

    expect(fn ()
        => app(TextToStructuredEvaluation::class)->evaluate(
            new TextToStructuredEvaluationRequest(
                subject: 'refusal case',
                text: 'Summarize the policy exception.',
                enabledDimensions: ['clarity'],
                promptVersion: '1.0.0',
            ),
        ))->toThrow(TextToStructuredEvaluationException::class, 'refused to return structured output');
});

it('throws a typed exception when the specialist returns refusal json in the refusal field', function () {
    $fakeRuntime = new FakeAiRuntime([
      new ExecutionResult(
          runId: 'runtime-run-002d',
          output: json_encode([
          'refusal' => 'I cannot provide the requested JSON response for this evaluation.',
        ], JSON_THROW_ON_ERROR),
          provider: 'openai-structured',
          model: 'gpt-test-structured',
      ),
    ]);

    app()->instance(AiRuntime::class, $fakeRuntime);

    expect(fn ()
        => app(TextToStructuredEvaluation::class)->evaluate(
            new TextToStructuredEvaluationRequest(
                subject: 'refusal json case',
                text: 'Summarize the policy exception.',
                enabledDimensions: ['clarity'],
                promptVersion: '1.0.0',
            ),
        ))->toThrow(TextToStructuredEvaluationException::class, 'refused to return structured output');
});

it('throws a typed exception when the specialist returns invalid json', function () {
    $fakeRuntime = new FakeAiRuntime([
      new ExecutionResult(
          runId: 'runtime-run-003',
          output: 'not-json',
          provider: 'openai-structured',
          model: 'gpt-test-structured',
      ),
    ]);

    app()->instance(AiRuntime::class, $fakeRuntime);

    expect(fn ()
        => app(TextToStructuredEvaluation::class)->evaluate(
            new TextToStructuredEvaluationRequest(
                subject: 'incident summary',
                text: 'The incident was resolved after the queue worker was restarted.',
                promptVersion: '1.0.0',
            ),
        ))->toThrow(TextToStructuredEvaluationException::class, 'must be valid JSON');
});
