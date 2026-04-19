<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Core\Providers\AuditedProviderCapabilityMatrix;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions\ProviderCapabilityConformanceException;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderCapabilityConformanceSuite;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ProviderDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;
use CreativeCrafts\LaravelAiAgentKit\Testing\Assertions\PackageAssertions;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeAiRuntime;
use Illuminate\Config\Repository;

it('derives the audited capability matrix for a configured provider profile', function (): void {
    $suite = providerCapabilityConformanceSuite(new Repository([
      'ai-agent-kit' => [
        'providers' => [
          'general-openai' => [
            'driver' => 'openai',
            'enabled' => true,
            'capabilities' => [
              'text_generation',
              'structured_output',
              'tool_execution',
              'memory_continuation',
            ],
            'options' => [],
          ],
        ],
      ],
    ]));

    expect($suite->conformedCapabilitiesForProfile('general-openai'))
      ->toBe([
        'text_generation',
        'structured_output',
        'tool_capable_execution',
        'memory_aware_continuation',
        'text_to_structured_evaluation',
      ]);
});

it('proves a declared structured-output profile through a deterministic runtime probe', function (): void {
    $suite = providerCapabilityConformanceSuite(new Repository([
      'ai-agent-kit' => [
        'providers' => [
          'general-openai' => [
            'driver' => 'openai',
            'enabled' => true,
            'capabilities' => [
              'text_generation',
              'structured_output',
            ],
            'options' => [],
          ],
        ],
      ],
    ]));

    $runtime = (new FakeAiRuntime())->queueCallback(
        static fn (ExecutionRequest $request): ExecutionResult => new ExecutionResult(
            runId: $request->runId,
            output: '{"status":"ok"}',
            provider: $request->provider,
            model: $request->model,
        ),
    );

    $suite->assertProfileConforms(
        providerProfile: 'general-openai',
        capability: 'structured_output',
        probe: function (ProviderDefinition $providerDefinition) use ($runtime): void {
          $result = $runtime->execute(
              new ExecutionRequest(
                  runId: 'run-conformance-structured-001',
                  prompt: 'Return a package-owned JSON object.',
                  provider: $providerDefinition->name,
                  model: 'fake-model',
              ),
          );

          if ($result->output !== '{"status":"ok"}') {
              throw new RuntimeException('Structured-output probe did not produce the expected deterministic result.');
          }
      },
    );

    PackageAssertions::assertRuntimeExecutedTimes($runtime, 1);
});

it('fails explicitly when a provider profile does not declare the audited capability requirements', function (): void {
    $suite = providerCapabilityConformanceSuite(new Repository([
      'ai-agent-kit' => [
        'providers' => [
          'text-only-anthropic' => [
            'driver' => 'anthropic',
            'enabled' => true,
            'capabilities' => ['text_generation'],
            'options' => [],
          ],
        ],
      ],
    ]));

    try {
        $suite->assertProfileConforms(
            providerProfile: 'text-only-anthropic',
            capability: 'structured_output',
            probe: static function (ProviderDefinition $providerDefinition): void {
              throw new RuntimeException(
                  sprintf(
                      'Probe should not run for [%s] when declared capability requirements are missing.',
                      $providerDefinition->name,
                  ),
              );
          },
        );

        throw new RuntimeException('Expected the conformance suite to reject the profile capability mismatch.');
    } catch (ProviderCapabilityConformanceException $exception) {
        expect($exception->getMessage())
          ->toBe(
              'Provider profile [text-only-anthropic] does not conform to audited capability [structured_output]; missing declared capabilities [structured_output].',
          );
    }
});

it('fails explicitly when a profile claims support but the deterministic probe shows otherwise', function (): void {
    $suite = providerCapabilityConformanceSuite(new Repository([
      'ai-agent-kit' => [
        'providers' => [
          'lying-openai' => [
            'driver' => 'openai',
            'enabled' => true,
            'capabilities' => [
              'text_generation',
              'structured_output',
            ],
            'options' => [],
          ],
        ],
      ],
    ]));

    $runtime = (new FakeAiRuntime())->queueCallback(
        static fn (ExecutionRequest $request): ExecutionResult => new ExecutionResult(
            runId: $request->runId,
            output: '',
            provider: $request->provider,
            model: $request->model,
        ),
    );

    try {
        $suite->assertProfileConforms(
            providerProfile: 'lying-openai',
            capability: 'structured_output',
            probe: function (ProviderDefinition $providerDefinition) use ($runtime): void {
              $result = $runtime->execute(
                  new ExecutionRequest(
                      runId: 'run-conformance-structured-002',
                      prompt: 'Return structured output.',
                      provider: $providerDefinition->name,
                      model: 'fake-model',
                  ),
              );

              if ($result->output === '') {
                  throw new RuntimeException('Probe did not observe deterministic structured output.');
              }
          },
        );

        throw new RuntimeException('Expected the conformance suite to wrap the deterministic probe failure.');
    } catch (ProviderCapabilityConformanceException $exception) {
        expect($exception->getMessage())
          ->toBe(
              'Provider profile [lying-openai] declared audited capability [structured_output] but the deterministic conformance probe failed.',
          )
          ->and($exception->getPrevious())->toBeInstanceOf(RuntimeException::class)
          ->and($exception->getPrevious()?->getMessage())->toBe('Probe did not observe deterministic structured output.');
    }
});

it('proves staged audio-to-text-to-evaluation support through deterministic stage probes', function (): void {
    $suite = providerCapabilityConformanceSuite(new Repository([
      'ai-agent-kit' => [
        'providers' => [
          'audio-transcriber' => [
            'driver' => 'openai',
            'enabled' => true,
            'capabilities' => ['audio_transcription'],
            'options' => [],
          ],
          'evaluation-openai' => [
            'driver' => 'openai',
            'enabled' => true,
            'capabilities' => [
              'text_generation',
              'structured_output',
            ],
            'options' => [],
          ],
        ],
      ],
    ]));

    $runtime = (new FakeAiRuntime())
      ->queueCallback(
          static fn (ExecutionRequest $request): ExecutionResult => new ExecutionResult(
              runId: $request->runId,
              output: 'transcribed audio text',
              provider: $request->provider,
              model: $request->model,
          ),
      )
      ->queueCallback(
          static fn (ExecutionRequest $request): ExecutionResult => new ExecutionResult(
              runId: $request->runId,
              output: '{"score":5}',
              provider: $request->provider,
              model: $request->model,
          ),
      );

    $suite->assertStagesConform(
        capability: 'audio_to_text_to_evaluation',
        profilesByStage: [
        'transcription' => 'audio-transcriber',
        'evaluation' => 'evaluation-openai',
      ],
        probe: function (array $providerDefinitionsByStage) use ($runtime): void {
          /** @var array<string, ProviderDefinition> $providerDefinitionsByStage */
          $transcriptionResult = $runtime->execute(
              new ExecutionRequest(
                  runId: 'run-conformance-audio-001',
                  prompt: 'Transcribe the audio payload.',
                  provider: $providerDefinitionsByStage['transcription']->name,
                  model: 'fake-transcription-model',
              ),
          );

          if ($transcriptionResult->output === '') {
              throw new RuntimeException('Audio transcription probe did not produce transcript text.');
          }

          $evaluationResult = $runtime->execute(
              new ExecutionRequest(
                  runId: 'run-conformance-audio-002',
                  prompt: $transcriptionResult->output,
                  provider: $providerDefinitionsByStage['evaluation']->name,
                  model: 'fake-evaluation-model',
              ),
          );

          if ($evaluationResult->output !== '{"score":5}') {
              throw new RuntimeException('Evaluation probe did not produce the expected deterministic result.');
          }
      },
    );

    PackageAssertions::assertRuntimeExecutedTimes($runtime, 2);
});

it('fails explicitly when a staged workflow uses incompatible provider profiles', function (): void {
    $suite = providerCapabilityConformanceSuite(new Repository([
      'ai-agent-kit' => [
        'providers' => [
          'audio-transcriber' => [
            'driver' => 'openai',
            'enabled' => true,
            'capabilities' => ['audio_transcription'],
            'options' => [],
          ],
          'text-only-anthropic' => [
            'driver' => 'anthropic',
            'enabled' => true,
            'capabilities' => ['text_generation'],
            'options' => [],
          ],
        ],
      ],
    ]));

    try {
        $suite->assertStagesConform(
            capability: 'audio_to_text_to_evaluation',
            profilesByStage: [
            'transcription' => 'audio-transcriber',
            'evaluation' => 'text-only-anthropic',
          ],
            probe: static function (array $providerDefinitionsByStage): void {
              /** @var array<string, ProviderDefinition> $providerDefinitionsByStage */
              throw new RuntimeException('Probe should not run when staged requirements are missing.');
          },
        );

        throw new RuntimeException('Expected the conformance suite to reject the staged capability mismatch.');
    } catch (ProviderCapabilityConformanceException $exception) {
        expect($exception->getMessage())
          ->toBe(
              'Staged provider profiles [transcription=audio-transcriber, evaluation=text-only-anthropic] do not conform to audited capability [audio_to_text_to_evaluation]; missing declared capabilities [evaluation: structured_output].',
          );
    }
});

function providerCapabilityConformanceSuite(Repository $config): ProviderCapabilityConformanceSuite
{
    return new ProviderCapabilityConformanceSuite(
        providerRegistry: new ConfiguredProviderRegistry($config),
        capabilityMatrix: new AuditedProviderCapabilityMatrix(),
    );
}
