<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\AudioToTextToEvaluationCoordinatorAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\AudioToTextToEvaluationTranscriptionAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\TextToStructuredEvaluationCoordinatorAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\TextToStructuredEvaluationSpecialistAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluation;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluationResult;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Exceptions\AudioToTextToEvaluationException;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Exceptions\TextToStructuredEvaluationException;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\AgentRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\TranscriptionRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Prompts\PromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\AgentProviderProfileSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\ContainerAgentRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionResult;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\SynchronousAgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\AuditedProviderCapabilityMatrix;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredAgentProviderProfileSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\DefaultProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Prompts\InMemoryPromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Prompts\PromptExecutionMapper;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAiRuntime;
use Illuminate\Support\Facades\Config;
use Laravel\Ai\AiServiceProvider;

beforeEach(function (): void {
    bootAudioToTextToEvaluationBlueprintTestbed(
        providers: audioToTextToEvaluationDefaultProviders(),
    );
});

it('transcribes and evaluates audio through one orchestration call', function (): void {
    $fakeRuntime = new FakeAiRuntime([
      new ExecutionResult(
          runId: 'audio-run-001',
          output: 'Please refund the unused portion of my subscription.',
          provider: 'openai-transcription',
          model: 'gpt-audio-test',
      ),
      new ExecutionResult(
          runId: 'audio-run-002',
          output: json_encode([
          'summary' => 'The transcript is clear and contains a direct refund request.',
          'recommended_action' => 'Approve the refund review workflow.',
          'confidence' => 0.94,
          'dimensions' => [
            'clarity' => [
              'score' => 5,
              'summary' => 'The refund intent is explicit.',
              'evidence' => ['The speaker directly asks for a refund.'],
            ],
            'accuracy' => [
              'score' => 4,
              'summary' => 'The request is consistent with a standard refund scenario.',
              'evidence' => ['No contradictory statements appear in the transcript.'],
            ],
          ],
        ], JSON_THROW_ON_ERROR),
          provider: 'openai-structured',
          model: 'gpt-structured-test',
      ),
    ]);

    app()->instance(AiRuntime::class, $fakeRuntime);

    $result = app(AudioToTextToEvaluation::class)->evaluate(
        new AudioToTextToEvaluationRequest(
            subject: 'refund call',
            audioReference: 's3://bucket/audio/refund-call.wav',
            audioMimeType: 'audio/wav',
            enabledDimensions: ['clarity', 'accuracy'],
            transcriptionPromptVersion: '1.0.0',
            evaluationPromptVersion: '1.0.0',
        ),
    );

    expect($result->subject)
      ->toBe('refund call')
      ->and($result->audioReference)->toBe('s3://bucket/audio/refund-call.wav')
      ->and($result->transcript)->toBe('Please refund the unused portion of my subscription.')
      ->and($result->summary)->toBe('The transcript is clear and contains a direct refund request.')
      ->and($result->recommendedAction)->toBe('Approve the refund review workflow.')
      ->and($result->confidence)->toBe(0.94)
      ->and($result->enabledDimensions)->toBe(['clarity', 'accuracy'])
      ->and($result->dimension('clarity')?->score)->toBe(5)
      ->and($result->transcriptionPromptName)->toBe('audio-to-text-to-evaluation.transcription')
      ->and($result->evaluationPromptName)->toBe('text-to-structured-evaluation.specialist')
      ->and($result->finalAgent)->toBe(AudioToTextToEvaluationCoordinatorAgent::KEY)
      ->and($result->trace)->toHaveCount(7)
      ->and($result->trace[0]->agentKey)->toBe(AudioToTextToEvaluationCoordinatorAgent::KEY)
      ->and($result->trace[0]->targetAgent)->toBe(AudioToTextToEvaluationTranscriptionAgent::KEY)
      ->and($result->trace[1]->providerProfile)->toBe('openai-transcription')
      ->and($result->trace[2]->targetAgent)->toBe(TextToStructuredEvaluationCoordinatorAgent::KEY)
      ->and($result->trace[3]->targetAgent)->toBe(TextToStructuredEvaluationSpecialistAgent::KEY)
      ->and($result->trace[4]->providerProfile)->toBe('openai-structured');

    expect($fakeRuntime->requests())->toHaveCount(2);

    $requests = $fakeRuntime->requests();

    expect($requests[0])
      ->toBeInstanceOf(ExecutionRequest::class)
      ->and($requests[0]->provider)->toBe('openai-transcription')
      ->and($requests[0]->prompt)->toContain('Transcribe the following audio for refund call.')
      ->and($requests[0]->prompt)->toContain('Audio reference: s3://bucket/audio/refund-call.wav')
      ->and($requests[1]->provider)->toBe('openai-structured')
      ->and($requests[1]->prompt)->toContain('Evaluate the following text for refund call.')
      ->and($requests[1]->prompt)->toContain('Enabled dimensions: clarity, accuracy')
      ->and($requests[1]->prompt)->toContain('Please refund the unused portion of my subscription.');
});

it('uses the modality transcription runtime when audio_reference is decodable base64', function (): void {
    app()->register(AiServiceProvider::class);

    /** @var array<string, mixed> $ai */
    $ai = require __DIR__ . '/../vendor/laravel/ai/config/ai.php';
    Config::set('ai', $ai);
    Config::set('ai.default', 'openai');
    Config::set('ai.default_for_transcription', 'openai');
    Config::set('ai.providers', [
      'openai' => [
        'driver' => 'openai',
        'key' => 'test-key-for-ci',
      ],
      'openai-transcription' => [
        'driver' => 'openai',
        'key' => 'test-key-for-ci',
      ],
    ]);

    // Remove Transcription::fake(['modality transcript line'])->preventStrayTranscriptions();
    $transcriptionRuntime = new AudioBlueprintRecordingTranscriptionRuntime('modality transcript line');
    app()->instance(TranscriptionRuntime::class, $transcriptionRuntime);

    $evaluationJson = json_encode([
      'summary' => 'ok',
      'recommended_action' => 'act',
      'confidence' => 0.9,
      'dimensions' => [
        'clarity' => [
          'score' => 5,
          'summary' => 'clear',
          'evidence' => ['e'],
        ],
      ],
    ], JSON_THROW_ON_ERROR);

    $fakeRuntime = new FakeAiRuntime([
      new ExecutionResult(
          runId: 'audio-run-eval-only',
          output: $evaluationJson,
          provider: 'openai-structured',
          model: 'gpt-structured-test',
      ),
    ]);

    app()->instance(AiRuntime::class, $fakeRuntime);

    $result = app(AudioToTextToEvaluation::class)->evaluate(
        new AudioToTextToEvaluationRequest(
            subject: 'base64 clip',
            audioReference: base64_encode('fake-audio-bytes'),
            audioMimeType: 'audio/wav',
            enabledDimensions: ['clarity'],
            transcriptionPromptVersion: '1.0.0',
            evaluationPromptVersion: '1.0.0',
        ),
    );

    expect($result->transcript)
      ->toBe('modality transcript line')
      ->and($fakeRuntime->requests())->toHaveCount(1)
      ->and($transcriptionRuntime->requests)->toHaveCount(1)
      ->and($transcriptionRuntime->requests[0]->prompt)->toContain('Transcribe the following audio for base64 clip.')
      ->and($transcriptionRuntime->requests[0]->metadata['transcription_prompt_name'] ?? null)
      ->toBe('audio-to-text-to-evaluation.transcription')
      ->and($transcriptionRuntime->requests[0]->metadata['transcription_prompt_version'] ?? null)
      ->toBe('1.0.0');
});

it('preserves the same package-owned result semantics across mixed-provider stage combinations that satisfy the audited capability matrix', function (): void {
    $payload = audioToTextToEvaluationParityPayload();
    $transcript = 'Please confirm whether the refund request can be approved today.';

    $scenarios = [
      [
        'transcription_profile' => 'openai-transcription',
        'evaluation_profile' => 'anthropic-structured',
        'evaluation_output' => json_encode([
          'data' => $payload,
        ], JSON_THROW_ON_ERROR),
      ],
      [
        'transcription_profile' => 'gemini-transcription',
        'evaluation_profile' => 'openai-structured',
        'evaluation_output' => json_encode($payload, JSON_THROW_ON_ERROR),
      ],
      [
        'transcription_profile' => 'xai-transcription',
        'evaluation_profile' => 'gemini-structured',
        'evaluation_output' => <<<OUTPUT
                                   Provider response:
                                   
                                   ```json
                                   {
                                     "summary": "The transcript is specific and easy to action.",
                                     "recommended_action": "Approve the refund review workflow.",
                                     "confidence": 0.91,
                                     "dimensions": {
                                       "clarity": {
                                         "score": 5,
                                         "summary": "The refund intent is explicit in the transcript.",
                                         "evidence": [
                                           "The caller directly asks whether the refund can be approved today."
                                         ]
                                       }
                                     }
                                   }
                                   ```
                                   OUTPUT,
      ],
    ];

    foreach ($scenarios as $scenario) {
        $transcriptionProfile = $scenario['transcription_profile'];
        $evaluationProfile = $scenario['evaluation_profile'];

        bootAudioToTextToEvaluationBlueprintTestbed(
            providers: audioToTextToEvaluationProvidersOrderedFor(
                transcriptionProfile: $transcriptionProfile,
                evaluationProfile: $evaluationProfile,
            ),
        );

        assertAudioToTextToEvaluationStageProfilesConform(
            transcriptionProfile: $transcriptionProfile,
            evaluationProfile: $evaluationProfile,
        );

        $fakeRuntime = new FakeAiRuntime([
          new ExecutionResult(
              runId: sprintf('audio-run-parity-transcription-%s', $transcriptionProfile),
              output: $transcript,
              provider: $transcriptionProfile,
              model: 'gpt-audio-test',
          ),
          new ExecutionResult(
              runId: sprintf('audio-run-parity-evaluation-%s', $evaluationProfile),
              output: $scenario['evaluation_output'],
              provider: $evaluationProfile,
              model: 'gpt-structured-test',
          ),
        ]);

        app()->instance(AiRuntime::class, $fakeRuntime);

        $result = app(AudioToTextToEvaluation::class)->evaluate(
            new AudioToTextToEvaluationRequest(
                subject: 'mixed stage parity case',
                audioReference: 's3://bucket/audio/mixed-stage.wav',
                audioMimeType: 'audio/wav',
                enabledDimensions: ['clarity'],
                transcriptionPromptVersion: '1.0.0',
                evaluationPromptVersion: '1.0.0',
            ),
        );

        expect(audioToTextToEvaluationParitySnapshot($result))
          ->toBe(
              audioToTextToEvaluationExpectedParitySnapshot(
                  subject: 'mixed stage parity case',
                  audioReference: 's3://bucket/audio/mixed-stage.wav',
                  transcript: $transcript,
              ),
          )
          ->and($result->trace[1]->providerProfile)->toBe($transcriptionProfile)
          ->and($result->trace[4]->providerProfile)->toBe($evaluationProfile);

        $requests = $fakeRuntime->requests();

        expect($requests)
          ->toHaveCount(2)
          ->and($requests[0]->provider)->toBe($transcriptionProfile)
          ->and($requests[1]->provider)->toBe($evaluationProfile);
    }
});

it('repairs wrapped structured evaluation output during the audio workflow', function (): void {
    $fakeRuntime = new FakeAiRuntime([
      new ExecutionResult(
          runId: 'audio-run-001b',
          output: 'The caller would like a refund before renewal.',
          provider: 'openai-transcription',
          model: 'gpt-audio-test',
      ),
      new ExecutionResult(
          runId: 'audio-run-002b',
          output: <<<OUTPUT
            The transcript has been evaluated successfully.
            
            {
              "response": {
                "summary": "The transcript contains a clear refund request.",
                "recommended_action": "Escalate to billing review.",
                "confidence": 0.89,
                "dimensions": {
                  "clarity": {
                    "score": 5,
                    "summary": "The speaker clearly states the desired outcome.",
                    "evidence": ["The request for a refund is explicit."]
                  }
                }
              }
            }
            OUTPUT,
          provider: 'openai-structured',
          model: 'gpt-structured-test',
      ),
    ]);

    app()->instance(AiRuntime::class, $fakeRuntime);

    $result = app(AudioToTextToEvaluation::class)->evaluate(
        new AudioToTextToEvaluationRequest(
            subject: 'repairable audio flow',
            audioReference: 's3://bucket/audio/repairable.wav',
            audioMimeType: 'audio/wav',
            enabledDimensions: ['clarity'],
            transcriptionPromptVersion: '1.0.0',
            evaluationPromptVersion: '1.0.0',
        ),
    );

    expect($result->transcript)
      ->toBe('The caller would like a refund before renewal.')
      ->and($result->summary)->toBe('The transcript contains a clear refund request.')
      ->and($result->recommendedAction)->toBe('Escalate to billing review.')
      ->and($result->confidence)->toBe(0.89)
      ->and($result->dimension('clarity')?->score)->toBe(5);
});

it('keeps the audio blueprint result schema fixed while preserving the transcript', function (): void {
    $fakeRuntime = new FakeAiRuntime([
      new ExecutionResult(
          runId: 'audio-run-003',
          output: 'The feature rollout completed successfully with no incidents.',
          provider: 'openai-transcription',
          model: 'gpt-audio-test',
      ),
      new ExecutionResult(
          runId: 'audio-run-004',
          output: json_encode([
          'summary' => 'The transcript is concise but misses operational context.',
          'recommended_action' => 'Add deployment timing and owner details.',
          'confidence' => 0.83,
          'dimensions' => [
            'completeness' => [
              'score' => 2,
              'summary' => 'The statement lacks rollout detail.',
              'evidence' => ['No timeline or owner is mentioned.'],
            ],
          ],
        ], JSON_THROW_ON_ERROR),
          provider: 'openai-structured',
          model: 'gpt-structured-test',
      ),
    ]);

    app()->instance(AiRuntime::class, $fakeRuntime);

    $result = app(AudioToTextToEvaluation::class)->evaluate(
        new AudioToTextToEvaluationRequest(
            subject: 'release voice memo',
            audioReference: 's3://bucket/audio/release-memo.mp3',
            audioMimeType: 'audio/mpeg',
            enabledDimensions: ['completeness'],
            transcriptionPromptVersion: '1.0.0',
            evaluationPromptVersion: '1.0.0',
        ),
    );

    expect($result->toArray()['audio_reference'])
      ->toBe('s3://bucket/audio/release-memo.mp3')
      ->and($result->toArray()['transcript'])->toBe('The feature rollout completed successfully with no incidents.')
      ->and(array_keys($result->dimensions))->toBe(['completeness'])
      ->and($result->toArray()['dimensions']['completeness']['score'])->toBe(2);
});

it('routes each stage to the next compatible provider profile when earlier compatible stage profiles are disabled', function (): void {
    $providers = audioToTextToEvaluationDefaultProviders();
    $providers['openai-transcription']['enabled'] = false;
    $providers['openai-structured']['enabled'] = false;

    bootAudioToTextToEvaluationBlueprintTestbed(
        providers: $providers,
    );

    assertAudioToTextToEvaluationStageProfilesConform(
        transcriptionProfile: 'gemini-transcription',
        evaluationProfile: 'anthropic-structured',
    );

    $transcript = 'The caller needs the billing team to approve the refund today.';

    $fakeRuntime = new FakeAiRuntime([
      new ExecutionResult(
          runId: 'audio-run-fallback-001',
          output: $transcript,
          provider: 'gemini-transcription',
          model: 'gpt-audio-test',
      ),
      new ExecutionResult(
          runId: 'audio-run-fallback-002',
          output: json_encode(audioToTextToEvaluationParityPayload(), JSON_THROW_ON_ERROR),
          provider: 'anthropic-structured',
          model: 'gpt-structured-test',
      ),
    ]);

    app()->instance(AiRuntime::class, $fakeRuntime);

    $result = app(AudioToTextToEvaluation::class)->evaluate(
        new AudioToTextToEvaluationRequest(
            subject: 'stage fallback case',
            audioReference: 's3://bucket/audio/stage-fallback.wav',
            audioMimeType: 'audio/wav',
            enabledDimensions: ['clarity'],
            transcriptionPromptVersion: '1.0.0',
            evaluationPromptVersion: '1.0.0',
        ),
    );

    expect($result->trace[1]->providerProfile)
      ->toBe('gemini-transcription')
      ->and($result->trace[4]->providerProfile)->toBe('anthropic-structured')
      ->and(audioToTextToEvaluationParitySnapshot($result))
      ->toBe(
          audioToTextToEvaluationExpectedParitySnapshot(
              subject: 'stage fallback case',
              audioReference: 's3://bucket/audio/stage-fallback.wav',
              transcript: $transcript,
          ),
      );

    $requests = $fakeRuntime->requests();

    expect($requests)
      ->toHaveCount(2)
      ->and($requests[0]->provider)->toBe('gemini-transcription')
      ->and($requests[1]->provider)->toBe('anthropic-structured');
});

it('throws a typed exception when transcription produces an empty transcript', function (): void {
    $fakeRuntime = new FakeAiRuntime([
      new ExecutionResult(
          runId: 'audio-run-005',
          output: '   ',
          provider: 'openai-transcription',
          model: 'gpt-audio-test',
      ),
    ]);

    app()->instance(AiRuntime::class, $fakeRuntime);

    expect(fn ()
        => app(AudioToTextToEvaluation::class)->evaluate(
            new AudioToTextToEvaluationRequest(
                subject: 'empty audio',
                audioReference: 's3://bucket/audio/empty.wav',
                transcriptionPromptVersion: '1.0.0',
                evaluationPromptVersion: '1.0.0',
            ),
        ))->toThrow(AudioToTextToEvaluationException::class, 'transcription output must be a non-empty string');
});

it('fails fast when no enabled provider supports audio transcription', function (): void {
    bootAudioToTextToEvaluationBlueprintTestbed(
        providers: [
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
      ],
    );

    expect(fn ()
        => app(AudioToTextToEvaluation::class)->evaluate(
            new AudioToTextToEvaluationRequest(
                subject: 'missing transcription provider',
                audioReference: 's3://bucket/audio/missing-transcription.wav',
                transcriptionPromptVersion: '1.0.0',
                evaluationPromptVersion: '1.0.0',
            ),
        ))->toThrow(AudioToTextToEvaluationException::class, 'No enabled provider supports required capabilities [audio_transcription]');
});

it('fails explicitly when no enabled provider profile satisfies the structured evaluation stage requirements', function (): void {
    bootAudioToTextToEvaluationBlueprintTestbed(
        providers: [
        'openai-default' => [
          'driver' => 'openai',
          'enabled' => true,
          'capabilities' => ['text_generation'],
          'options' => [],
        ],
        'openai-transcription' => [
          'driver' => 'openai',
          'enabled' => true,
          'capabilities' => ['audio_transcription'],
          'options' => [],
        ],
        'text-only-evaluation' => [
          'driver' => 'anthropic',
          'enabled' => true,
          'capabilities' => ['text_generation'],
          'options' => [],
        ],
      ],
    );

    expect(fn ()
        => app(AudioToTextToEvaluation::class)->evaluate(
            new AudioToTextToEvaluationRequest(
                subject: 'missing structured output stage',
                audioReference: 's3://bucket/audio/missing-structured-output.wav',
                transcriptionPromptVersion: '1.0.0',
                evaluationPromptVersion: '1.0.0',
            ),
        ))->toThrow(
            TextToStructuredEvaluationException::class,
            'No enabled provider supports required capabilities [text_generation, structured_output].',
        );
});

it('fails fast on malformed delegated transcription payload', function (): void {
    $coordinator = app(AudioToTextToEvaluationCoordinatorAgent::class);
    $definition = $coordinator->definition();

    $context = new AgentExecutionContext(
        orchestrationId: 'orch-malformed-001',
        executionId: 'exec-malformed-001',
        parentExecutionId: null,
        agent: new AgentDefinition(
            key: $definition->key,
            displayName: $definition->displayName,
            requiredCapabilities: $definition->requiredCapabilities,
            primaryProviderProfile: $definition->primaryProviderProfile,
            fallbackProviderProfiles: $definition->fallbackProviderProfiles,
            delegationTargets: $definition->delegationTargets,
        ),
        providerProfile: $definition->primaryProviderProfile,
        task: 'test malformed delegated transcription payload',
        payload: [
        'subject' => 'malformed delegated transcript',
        'audio_reference' => 's3://bucket/audio/malformed.wav',
        'delegated_agent' => AudioToTextToEvaluationCoordinatorAgent::TRANSCRIPTION_SPECIALIST_KEY,
        'delegated_result' => ['not_transcript' => 'missing transcript key'],
      ],
    );

    expect(fn () => $coordinator->handle($context))
      ->toThrow(AudioToTextToEvaluationException::class, 'transcription delegated result must contain a non-empty transcript');
});


final class AudioBlueprintRecordingTranscriptionRuntime implements TranscriptionRuntime
{
    /** @var list<TranscriptionRequest> */
    public array $requests = [];

    public function __construct(private readonly string $transcript)
    {
    }

    public function transcribe(TranscriptionRequest $request): TranscriptionResult
    {
        $this->requests[] = $request;

        return new TranscriptionResult(
            runId: $request->runId,
            transcript: $this->transcript,
            provider: $request->provider ?? 'openai',
            model: $request->model ?? 'gpt-4o-transcribe',
            promptTokens: 0,
            completionTokens: 0,
            metadata: $request->metadata,
        );
    }
}

function refreshAudioToTextToEvaluationBindings(): void
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
    app()->forgetInstance(TranscriptionRuntime::class);
    app()->forgetInstance(ContainerAgentRegistry::class);
    app()->forgetInstance(AgentRegistry::class);
}

/**
 * @param array<string, array{driver:string, enabled:bool, capabilities:list<string>, options:array<string, mixed>}> $providers
 * @param list<string>|null $failoverOrder
 */
function bootAudioToTextToEvaluationBlueprintTestbed(
    array $providers,
    string $defaultProvider = 'openai-default',
    ?array $failoverOrder = null,
): void {
    config()->set('ai-agent-kit.providers', $providers);
    config()->set('ai-agent-kit.default_provider', $defaultProvider);
    config()->set('ai-agent-kit.failover_order', $failoverOrder ?? array_keys($providers));

    refreshAudioToTextToEvaluationBindings();

    $promptRepository = new InMemoryPromptRepository([
      'audio-to-text-to-evaluation.transcription' => [
        '1.0.0' => <<<PROMPT
                       Transcribe the following audio for {{subject}}.
                       Audio reference: {{audio_reference}}
                       Audio mime type: {{audio_mime_type}}
                       PROMPT,
      ],
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
function audioToTextToEvaluationDefaultProviders(): array
{
    return [
      'openai-default' => [
        'driver' => 'openai',
        'enabled' => true,
        'capabilities' => ['text_generation'],
        'options' => [],
      ],
      'openai-transcription' => [
        'driver' => 'openai',
        'enabled' => true,
        'capabilities' => ['audio_transcription'],
        'options' => [],
      ],
      'gemini-transcription' => [
        'driver' => 'gemini',
        'enabled' => true,
        'capabilities' => ['audio_transcription'],
        'options' => [],
      ],
      'xai-transcription' => [
        'driver' => 'xai',
        'enabled' => true,
        'capabilities' => ['audio_transcription'],
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
function audioToTextToEvaluationProvidersOrderedFor(
    string $transcriptionProfile,
    string $evaluationProfile,
): array {
    $providers = audioToTextToEvaluationDefaultProviders();

    $ordered = [
      'openai-default' => $providers['openai-default'],
    ];

    foreach (
      [
        $transcriptionProfile,
        $evaluationProfile,
        'openai-transcription',
        'gemini-transcription',
        'xai-transcription',
        'openai-structured',
        'anthropic-structured',
        'gemini-structured',
      ] as $providerProfile
    ) {
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

function assertAudioToTextToEvaluationStageProfilesConform(
    string $transcriptionProfile,
    string $evaluationProfile,
): void {
    $matrix = new AuditedProviderCapabilityMatrix();
    $providerRegistry = app(ProviderRegistry::class);

    expect(
        $matrix->missingStageRequirements(
            [
          'transcription' => $providerRegistry->get($transcriptionProfile),
          'evaluation' => $providerRegistry->get($evaluationProfile),
        ],
            'audio_to_text_to_evaluation',
        ),
    )->toBe([]);
}

/**
 * @return array{
 *   summary:string,
 *   recommended_action:string,
 *   confidence:float,
 *   dimensions:array<string, array{score:int, summary:string, evidence:list<string>}>
 * }
 */
function audioToTextToEvaluationParityPayload(): array
{
    return [
      'summary' => 'The transcript is specific and easy to action.',
      'recommended_action' => 'Approve the refund review workflow.',
      'confidence' => 0.91,
      'dimensions' => [
        'clarity' => [
          'score' => 5,
          'summary' => 'The refund intent is explicit in the transcript.',
          'evidence' => ['The caller directly asks whether the refund can be approved today.'],
        ],
      ],
    ];
}

/**
 * @return array{
 *   subject:string,
 *   audio_reference:string,
 *   transcript:string,
 *   summary:string,
 *   recommended_action:string,
 *   confidence:float,
 *   enabled_dimensions:list<string>,
 *   dimensions:array<string, array{name:string, score:int, summary:string, evidence:list<string>}>,
 *   transcription_prompt_name:string,
 *   transcription_prompt_version:?string,
 *   evaluation_prompt_name:string,
 *   evaluation_prompt_version:?string,
 *   orchestration_summary:string,
 *   final_agent:string
 * }
 */
function audioToTextToEvaluationExpectedParitySnapshot(
    string $subject,
    string $audioReference,
    string $transcript,
): array {
    return [
      'subject' => $subject,
      'audio_reference' => $audioReference,
      'transcript' => $transcript,
      'summary' => 'The transcript is specific and easy to action.',
      'recommended_action' => 'Approve the refund review workflow.',
      'confidence' => 0.91,
      'enabled_dimensions' => ['clarity'],
      'dimensions' => [
        'clarity' => [
          'name' => 'clarity',
          'score' => 5,
          'summary' => 'The refund intent is explicit in the transcript.',
          'evidence' => ['The caller directly asks whether the refund can be approved today.'],
        ],
      ],
      'transcription_prompt_name' => 'audio-to-text-to-evaluation.transcription',
      'transcription_prompt_version' => '1.0.0',
      'evaluation_prompt_name' => 'text-to-structured-evaluation.specialist',
      'evaluation_prompt_version' => '1.0.0',
      'orchestration_summary' => 'AudioToTextToEvaluation coordinator finalized the structured result.',
      'final_agent' => AudioToTextToEvaluationCoordinatorAgent::KEY,
    ];
}

/**
 * @return array{
 *   subject:string,
 *   audio_reference:string,
 *   transcript:string,
 *   summary:string,
 *   recommended_action:string,
 *   confidence:float,
 *   enabled_dimensions:list<string>,
 *   dimensions:array<string, array{name:string, score:int, summary:string, evidence:list<string>}>,
 *   transcription_prompt_name:string,
 *   transcription_prompt_version:?string,
 *   evaluation_prompt_name:string,
 *   evaluation_prompt_version:?string,
 *   orchestration_summary:string,
 *   final_agent:string
 * }
 */
function audioToTextToEvaluationParitySnapshot(AudioToTextToEvaluationResult $result): array
{
    return [
      'subject' => $result->subject,
      'audio_reference' => $result->audioReference,
      'transcript' => $result->transcript,
      'summary' => $result->summary,
      'recommended_action' => $result->recommendedAction,
      'confidence' => $result->confidence,
      'enabled_dimensions' => $result->enabledDimensions,
      'dimensions' => array_map(
          static fn ($dimension): array => $dimension->toArray(),
          $result->dimensions,
      ),
      'transcription_prompt_name' => $result->transcriptionPromptName,
      'transcription_prompt_version' => $result->transcriptionPromptVersion,
      'evaluation_prompt_name' => $result->evaluationPromptName,
      'evaluation_prompt_version' => $result->evaluationPromptVersion,
      'orchestration_summary' => $result->orchestrationSummary,
      'final_agent' => $result->finalAgent,
    ];
}
