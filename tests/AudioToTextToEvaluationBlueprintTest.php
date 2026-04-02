<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\AudioToTextToEvaluationCoordinatorAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\AudioToTextToEvaluationTranscriptionAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\TextToStructuredEvaluationCoordinatorAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Agents\TextToStructuredEvaluationSpecialistAgent;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluation;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\AudioToTextToEvaluationRequest;
use CreativeCrafts\LaravelAiAgentKit\Blueprints\Exceptions\AudioToTextToEvaluationException;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\AgentRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\AiRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\AgentOrchestrator;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Prompts\PromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\AgentProviderProfileSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
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
      'openai-transcription' => [
        'driver' => 'openai',
        'enabled' => true,
        'capabilities' => ['audio_transcription'],
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
    config()->set('ai-agent-kit.failover_order', ['openai-default', 'openai-transcription', 'openai-structured']);

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

    app(AgentRegistry::class)->registerMany([
      AudioToTextToEvaluationCoordinatorAgent::class,
      AudioToTextToEvaluationTranscriptionAgent::class,
      TextToStructuredEvaluationCoordinatorAgent::class,
      TextToStructuredEvaluationSpecialistAgent::class,
    ]);
});

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
    app()->forgetInstance(ContainerAgentRegistry::class);
    app()->forgetInstance(AgentRegistry::class);
}

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
      ->and($result->trace[2]->targetAgent)->toBe(TextToStructuredEvaluationCoordinatorAgent::KEY)
      ->and($result->trace[3]->targetAgent)->toBe(TextToStructuredEvaluationSpecialistAgent::KEY);

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

    refreshAudioToTextToEvaluationBindings();

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
