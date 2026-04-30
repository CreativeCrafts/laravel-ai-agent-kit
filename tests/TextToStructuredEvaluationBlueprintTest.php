<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\TextToStructuredEvaluationCoordinatorAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\TextToStructuredEvaluationSpecialistAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Exceptions\TextToStructuredEvaluationException;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluation;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluationResult;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\AgentRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Prompts\PromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\AgentProviderProfileSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\ContainerAgentRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\SynchronousAgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\AuditedProviderCapabilityMatrix;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredAgentProviderProfileSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\DefaultProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\StructuredEvaluationJsonSchema;
use CreativeCrafts\LaravelAiAgentKit\Prompts\InMemoryPromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Prompts\PromptExecutionMapper;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAiRuntime;
use Laravel\Ai\ObjectSchema;

beforeEach(function (): void {
    bootTextToStructuredEvaluationBlueprintTestbed(
        providers: textToStructuredEvaluationDefaultProviders(),
    );
});

it('returns one final structured result from a single blueprint call over the orchestrator', function (): void {
    $structuredPayload = [
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
    ];

    $fakeRuntime = new FakeAiRuntime([
      new ExecutionResult(
          runId: 'runtime-run-001',
          output: json_encode($structuredPayload, JSON_THROW_ON_ERROR),
          provider: 'openai-structured',
          model: 'gpt-test-structured',
          structuredOutput: $structuredPayload,
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
      ->and($result->trace[1]->providerProfile)->toBe('openai-structured')
      ->and($result->trace[2]->agentKey)->toBe(TextToStructuredEvaluationCoordinatorAgent::KEY)
      ->and($result->structuredEvaluationPath)->toBe('structured_output')
      ->and($result->structuredEvaluationRepaired)->toBeFalse()
      ->and($fakeRuntime)->toHaveRuntimeExecutions(1);

    $runtimeRequest = $fakeRuntime->lastRequest();

    expect($runtimeRequest)
      ->toBeInstanceOf(ExecutionRequest::class)
      ->and($runtimeRequest?->provider)->toBe('openai-structured')
      ->and($runtimeRequest?->prompt)->toContain('Evaluate the following text for customer support reply.')
      ->and($runtimeRequest?->prompt)->toContain('Enabled dimensions: clarity, accuracy')
      ->and($runtimeRequest?->metadata['prompt_name'])->toBe('text-to-structured-evaluation.specialist')
      ->and($runtimeRequest?->metadata['prompt_version'])->toBe('1.0.0')
      ->and($runtimeRequest?->schema)->toBeInstanceOf(ObjectSchema::class)
      ->and($runtimeRequest?->schema->name())->toBe(StructuredEvaluationJsonSchema::OBJECT_SCHEMA_NAME);
});

it('keeps the result schema fixed while allowing callers to enable a subset of dimensions', function (): void {
    $structuredPayload = [
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
    ];

    $fakeRuntime = new FakeAiRuntime([
      new ExecutionResult(
          runId: 'runtime-run-002',
          output: json_encode($structuredPayload, JSON_THROW_ON_ERROR),
          provider: 'openai-structured',
          model: 'gpt-test-structured',
          structuredOutput: $structuredPayload,
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
      ->and($fakeRuntime->lastRequest()?->prompt)->toContain('Enabled dimensions: completeness')
      ->and($result->structuredEvaluationPath)->toBe('structured_output');
});

it('preserves the same package-owned result semantics across matrix-valid provider profiles', function (): void {
    $payload = textToStructuredEvaluationParityPayload();

    $scenarios = [
      'openai-structured' => json_encode($payload, JSON_THROW_ON_ERROR),
      'anthropic-structured' => json_encode([
        'data' => $payload,
      ], JSON_THROW_ON_ERROR),
      'gemini-structured' => <<<OUTPUT
                                 Provider response:
                                 
                                 ```json
                                 {
                                   "summary": "The response is specific and easy to action.",
                                   "recommended_action": "Send the response as drafted.",
                                   "confidence": 0.88,
                                   "dimensions": {
                                     "clarity": {
                                       "score": 5,
                                       "summary": "The wording is direct and unambiguous.",
                                       "evidence": [
                                         "The next step is stated clearly in the first sentence."
                                       ]
                                     }
                                   }
                                 }
                                 ```
                                 OUTPUT,
    ];

    foreach ($scenarios as $providerProfile => $output) {
        bootTextToStructuredEvaluationBlueprintTestbed(
            providers: textToStructuredEvaluationProvidersOrderedFor($providerProfile),
        );

        assertTextToStructuredEvaluationProfileConforms($providerProfile);

        $fakeRuntime = new FakeAiRuntime([
          new ExecutionResult(
              runId: sprintf('runtime-run-parity-%s', $providerProfile),
              output: $output,
              provider: $providerProfile,
              model: 'gpt-test-structured',
              structuredOutput: textToStructuredEvaluationParityPayload(),
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

        expect(textToStructuredEvaluationParitySnapshot($result))
          ->toBe(textToStructuredEvaluationExpectedParitySnapshot('repairable response'))
          ->and($result->trace[1]->providerProfile)->toBe($providerProfile)
          ->and($fakeRuntime->lastRequest()?->provider)->toBe($providerProfile);
    }
});

it('falls back to text normalization when structured output is present but invalid', function (): void {
    $fakeRuntime = new FakeAiRuntime([
      new ExecutionResult(
          runId: 'runtime-run-invalid-structured',
          output: json_encode(textToStructuredEvaluationParityPayload(), JSON_THROW_ON_ERROR),
          provider: 'openai-structured',
          model: 'gpt-test-structured',
          structuredOutput: ['summary' => 'incomplete'],
      ),
    ]);

    app()->instance(AiRuntime::class, $fakeRuntime);

    $result = app(TextToStructuredEvaluation::class)->evaluate(
        new TextToStructuredEvaluationRequest(
            subject: 'fallback from bad structured',
            text: 'Please confirm whether the refund can be processed today.',
            enabledDimensions: ['clarity'],
            promptVersion: '1.0.0',
        ),
    );

    expect($result->structuredEvaluationPath)->toBe('text_normalization')
      ->and($result->structuredEvaluationRepaired)->toBeFalse()
      ->and($result->summary)->toBe('The response is specific and easy to action.');
});

it('repairs wrapped structured output returned by the specialist runtime response', function (): void {
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
      ->and($result->dimension('clarity')?->score)->toBe(5)
      ->and($result->structuredEvaluationPath)->toBe('text_normalization')
      ->and($result->structuredEvaluationRepaired)->toBeTrue();
});

it('routes to the next compatible provider profile when the first compatible specialist profile is disabled', function (): void {
    $providers = textToStructuredEvaluationDefaultProviders();
    $providers['openai-structured']['enabled'] = false;

    bootTextToStructuredEvaluationBlueprintTestbed(
        providers: $providers,
    );

    assertTextToStructuredEvaluationProfileConforms('anthropic-structured');

    $fakeRuntime = new FakeAiRuntime([
      new ExecutionResult(
          runId: 'runtime-run-fallback-001',
          output: json_encode(textToStructuredEvaluationParityPayload(), JSON_THROW_ON_ERROR),
          provider: 'anthropic-structured',
          model: 'gpt-test-structured',
          structuredOutput: textToStructuredEvaluationParityPayload(),
      ),
    ]);

    app()->instance(AiRuntime::class, $fakeRuntime);

    $result = app(TextToStructuredEvaluation::class)->evaluate(
        new TextToStructuredEvaluationRequest(
            subject: 'fallback case',
            text: 'Please confirm the refund policy.',
            enabledDimensions: ['clarity'],
            promptVersion: '1.0.0',
        ),
    );

    expect($result->trace[1]->providerProfile)
      ->toBe('anthropic-structured')
      ->and($fakeRuntime->lastRequest()?->provider)->toBe('anthropic-structured')
      ->and(textToStructuredEvaluationParitySnapshot($result))
      ->toBe(textToStructuredEvaluationExpectedParitySnapshot('fallback case'));
});

it('throws a typed exception when the specialist refuses to return structured output', function (): void {
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

it('throws a typed exception when the specialist returns refusal json in the refusal field', function (): void {
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

it('throws a typed exception when the specialist returns invalid json', function (): void {
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

it('throws a typed exception when no configured provider profile satisfies the text-to-structured-evaluation capability target', function (): void {
    bootTextToStructuredEvaluationBlueprintTestbed(
        providers: [
        'openai-default' => [
          'driver' => 'openai',
          'enabled' => true,
          'capabilities' => ['text_generation'],
          'options' => [],
        ],
        'structured-only' => [
          'driver' => 'openai',
          'enabled' => true,
          'capabilities' => ['structured_output'],
          'options' => [],
        ],
        'anthropic-text' => [
          'driver' => 'anthropic',
          'enabled' => true,
          'capabilities' => ['text_generation'],
          'options' => [],
        ],
      ],
    );

    expect(fn ()
        => app(TextToStructuredEvaluation::class)->evaluate(
            new TextToStructuredEvaluationRequest(
                subject: 'mismatch case',
                text: 'Please evaluate this content.',
                enabledDimensions: ['clarity'],
                promptVersion: '1.0.0',
            ),
        ))->toThrow(
            TextToStructuredEvaluationException::class,
            'No enabled provider supports required capabilities [text_generation, structured_output].',
        );
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

/**
 * @param array<string, array{driver:string, enabled:bool, capabilities:list<string>, options:array<string, mixed>}> $providers
 * @param list<string>|null $failoverOrder
 */
function bootTextToStructuredEvaluationBlueprintTestbed(
    array $providers,
    string $defaultProvider = 'openai-default',
    ?array $failoverOrder = null,
): void {
    config()->set('ai-agent-kit.providers', $providers);
    config()->set('ai-agent-kit.default_provider', $defaultProvider);
    config()->set('ai-agent-kit.failover_order', $failoverOrder ?? array_keys($providers));

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
}

/**
 * @return array<string, array{driver:string, enabled:bool, capabilities:list<string>, options:array<string, mixed>}>
 */
function textToStructuredEvaluationDefaultProviders(): array
{
    return [
      'openai-default' => [
        'driver' => 'openai',
        'enabled' => true,
        'capabilities' => ['text_generation'],
        'options' => [],
      ],
      'openai-structured' => [
        'driver' => 'openai',
        'enabled' => true,
        'capabilities' => ['text_generation', 'structured_output'],
        'options' => [],
      ],
      'anthropic-structured' => [
        'driver' => 'anthropic',
        'enabled' => true,
        'capabilities' => ['text_generation', 'structured_output'],
        'options' => [],
      ],
      'gemini-structured' => [
        'driver' => 'gemini',
        'enabled' => true,
        'capabilities' => ['text_generation', 'structured_output'],
        'options' => [],
      ],
    ];
}

/**
 * @return array<string, array{driver:string, enabled:bool, capabilities:list<string>, options:array<string, mixed>}>
 */
function textToStructuredEvaluationProvidersOrderedFor(string $structuredProfile): array
{
    $providers = textToStructuredEvaluationDefaultProviders();

    $ordered = [
      'openai-default' => $providers['openai-default'],
    ];

    foreach ([$structuredProfile, 'openai-structured', 'anthropic-structured', 'gemini-structured'] as $providerProfile) {
        if ($providerProfile === 'openai-default') {
            continue;
        }

        if (!array_key_exists($providerProfile, $providers)) {
            continue;
        }

        if (array_key_exists($providerProfile, $ordered)) {
            continue;
        }

        $ordered[$providerProfile] = $providers[$providerProfile];
    }

    return $ordered;
}

function assertTextToStructuredEvaluationProfileConforms(string $providerProfile): void
{
    $matrix = new AuditedProviderCapabilityMatrix();
    $provider = app(ProviderRegistry::class)->get($providerProfile);

    expect($matrix->conformedCapabilitiesForProfile($provider))
      ->toContain('text_to_structured_evaluation');
}

/**
 * @return array{
 *   summary:string,
 *   recommended_action:string,
 *   confidence:float,
 *   dimensions:array<string, array{score:int, summary:string, evidence:list<string>}>
 * }
 */
function textToStructuredEvaluationParityPayload(): array
{
    return [
      'summary' => 'The response is specific and easy to action.',
      'recommended_action' => 'Send the response as drafted.',
      'confidence' => 0.88,
      'dimensions' => [
        'clarity' => [
          'score' => 5,
          'summary' => 'The wording is direct and unambiguous.',
          'evidence' => ['The next step is stated clearly in the first sentence.'],
        ],
      ],
    ];
}

/**
 * @return array{
 *   subject:string,
 *   summary:string,
 *   recommended_action:string,
 *   confidence:float,
 *   enabled_dimensions:list<string>,
 *   dimensions:array<string, array{name:string, score:int, summary:string, evidence:list<string>}>,
 *   orchestration_summary:string,
 *   final_agent:string,
 *   prompt_name:string,
 *   prompt_version:?string,
 *   structured_evaluation_path:?string,
 *   structured_evaluation_repaired:bool
 * }
 */
function textToStructuredEvaluationExpectedParitySnapshot(string $subject): array
{
    return [
      'subject' => $subject,
      'summary' => 'The response is specific and easy to action.',
      'recommended_action' => 'Send the response as drafted.',
      'confidence' => 0.88,
      'enabled_dimensions' => ['clarity'],
      'dimensions' => [
        'clarity' => [
          'name' => 'clarity',
          'score' => 5,
          'summary' => 'The wording is direct and unambiguous.',
          'evidence' => ['The next step is stated clearly in the first sentence.'],
        ],
      ],
      'orchestration_summary' => 'TextToStructuredEvaluation coordinator finalized the structured result.',
      'final_agent' => TextToStructuredEvaluationCoordinatorAgent::KEY,
      'prompt_name' => 'text-to-structured-evaluation.specialist',
      'prompt_version' => '1.0.0',
      'structured_evaluation_path' => 'structured_output',
      'structured_evaluation_repaired' => false,
    ];
}

/**
 * @return array{
 *   subject:string,
 *   summary:string,
 *   recommended_action:string,
 *   confidence:float,
 *   enabled_dimensions:list<string>,
 *   dimensions:array<string, array{name:string, score:int, summary:string, evidence:list<string>}>,
 *   orchestration_summary:string,
 *   final_agent:string,
 *   prompt_name:string,
 *   prompt_version:?string,
 *   structured_evaluation_path:?string,
 *   structured_evaluation_repaired:bool
 * }
 */
function textToStructuredEvaluationParitySnapshot(TextToStructuredEvaluationResult $result): array
{
    return [
      'subject' => $result->subject,
      'summary' => $result->summary,
      'recommended_action' => $result->recommendedAction,
      'confidence' => $result->confidence,
      'enabled_dimensions' => $result->enabledDimensions,
      'dimensions' => array_map(
          static fn ($dimension): array => $dimension->toArray(),
          $result->dimensions,
      ),
      'orchestration_summary' => $result->orchestrationSummary,
      'final_agent' => $result->finalAgent,
      'prompt_name' => $result->promptName,
      'prompt_version' => $result->promptVersion,
      'structured_evaluation_path' => $result->structuredEvaluationPath,
      'structured_evaluation_repaired' => $result->structuredEvaluationRepaired,
    ];
}
