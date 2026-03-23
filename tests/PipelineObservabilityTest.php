<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineRunner;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineStep;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\FailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Providers\ProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Exceptions\PipelineExecutionException;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\PipelineBuilder;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\SynchronousPipelineRunner;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredFailoverProviderSelector;
use CreativeCrafts\LaravelAiAgentKit\Core\Providers\ConfiguredProviderRegistry;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\PipelineCompleted;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\PipelineStarted;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\PipelineStepCompleted;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\PipelineStepFailed;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\PipelineStepStarted;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\ProviderFailoverResolved;
use Illuminate\Support\Facades\Event;

it('emits redacted pipeline lifecycle events during successful execution', function () {
    Event::fake([
      PipelineStarted::class,
      PipelineStepStarted::class,
      PipelineStepCompleted::class,
      PipelineCompleted::class,
    ]);
    forgetResolvedPipelineRunner();

    $pipeline = PipelineBuilder::make()
      ->addStep(
          new class () implements PipelineStep {
            public function handle(RunContext $context): RunContext
            {
                return $context
                  ->withStateValue('normalized', true)
                  ->withMetadataValue('result', 'completed')
                  ->incrementStepCount();
            }
        },
      )
      ->build();

    $runner = app(PipelineRunner::class);
    $context = new RunContext(
        runId: 'run-observability-success',
        input: ['secret' => 'raw-user-content', 'safe_key' => 'visible-only-by-key'],
        metadata: ['token' => 'do-not-log-this'],
        selectedProvider: 'primary',
    );

    $result = $runner->run($pipeline, $context);

    expect($result->stepCount)->toBe(1);

    Event::assertDispatched(PipelineStarted::class, function (PipelineStarted $event): bool {
        expect($event->runId)
          ->toBe('run-observability-success')
          ->and($event->totalSteps)->toBe(1)
          ->and($event->selectedProvider)->toBe('primary')
          ->and($event->inputKeys)->toBe(['[redacted-key]', 'safe_key'])
          ->and($event->metadataKeys)->toBe(['[redacted-key]'])
          ->and(property_exists($event, 'input'))->toBeFalse();

        return true;
    });

    Event::assertDispatched(PipelineStepStarted::class, function (PipelineStepStarted $event): bool {
        expect($event->runId)
          ->toBe('run-observability-success')
          ->and($event->stepIndex)->toBe(1)
          ->and($event->stateKeys)->toBe([])
          ->and($event->metadataKeys)->toBe(['[redacted-key]']);

        return true;
    });

    Event::assertDispatched(PipelineStepCompleted::class, function (PipelineStepCompleted $event): bool {
        expect($event->runId)
          ->toBe('run-observability-success')
          ->and($event->stepIndex)->toBe(1)
          ->and($event->stepCount)->toBe(1)
          ->and($event->stateKeys)->toBe(['normalized'])
          ->and($event->metadataKeys)->toBe(['[redacted-key]', 'result']);

        return true;
    });

    Event::assertDispatched(PipelineCompleted::class, function (PipelineCompleted $event): bool {
        expect($event->runId)
          ->toBe('run-observability-success')
          ->and($event->totalSteps)->toBe(1)
          ->and($event->toolCallCount)->toBe(0)
          ->and($event->stateKeys)->toBe(['normalized'])
          ->and($event->metadataKeys)->toBe(['[redacted-key]', 'result'])
          ->and(property_exists($event, 'state'))->toBeFalse();

        return true;
    });
});

it('emits a step failure event without raw exception payload content', function () {
    Event::fake([
      PipelineStarted::class,
      PipelineStepStarted::class,
      PipelineStepFailed::class,
      PipelineCompleted::class,
    ]);
    forgetResolvedPipelineRunner();

    $pipeline = PipelineBuilder::make()
      ->addStep(
          new class () implements PipelineStep {
            public function handle(RunContext $context): RunContext
            {
                throw new RuntimeException('sensitive-failure-message');
            }
        },
      )
      ->build();

    $runner = app(PipelineRunner::class);

    expect(fn (): RunContext => $runner->run($pipeline, new RunContext(runId: 'run-observability-failure')))
      ->toThrow(PipelineExecutionException::class);

    Event::assertDispatched(PipelineStepFailed::class, function (PipelineStepFailed $event): bool {
        expect($event->runId)
          ->toBe('run-observability-failure')
          ->and($event->stepIndex)->toBe(1)
          ->and($event->exceptionClass)->toBe(RuntimeException::class)
          ->and(property_exists($event, 'exceptionMessage'))->toBeFalse();

        return true;
    });

    Event::assertNotDispatched(PipelineCompleted::class);
});

it('emits redacted failover telemetry without provider option payloads', function () {
    Event::fake([ProviderFailoverResolved::class]);

    config()->set('ai-agent-kit.providers', [
      'primary' => [
        'driver' => 'null',
        'enabled' => true,
        'options' => ['api_key' => 'secret-value'],
      ],
      'backup' => [
        'driver' => 'null',
        'enabled' => true,
        'options' => ['api_key' => 'other-secret'],
      ],
    ]);
    config()->set('ai-agent-kit.failover_order', ['primary', 'backup']);
    forgetResolvedProviderServices();

    /** @var FailoverProviderSelector $selector */
    $selector = app(FailoverProviderSelector::class);
    $nextProvider = $selector->nextAfter('primary');

    expect($nextProvider)->not
      ->toBeNull()
      ->and($nextProvider?->name)->toBe('backup');

    Event::assertDispatched(ProviderFailoverResolved::class, function (ProviderFailoverResolved $event): bool {
        expect($event->currentProvider)
          ->toBe('primary')
          ->and($event->nextProvider)->toBe('backup')
          ->and($event->orderedProviders)->toBe(['primary', 'backup'])
          ->and(property_exists($event, 'options'))->toBeFalse();

        return true;
    });
});

function forgetResolvedPipelineRunner(): void
{
    app()->forgetInstance(PipelineRunner::class);
    app()->forgetInstance(SynchronousPipelineRunner::class);
}

function forgetResolvedProviderServices(): void
{
    app()->forgetInstance(FailoverProviderSelector::class);
    app()->forgetInstance(ConfiguredFailoverProviderSelector::class);
    app()->forgetInstance(ProviderRegistry::class);
    app()->forgetInstance(ConfiguredProviderRegistry::class);
}
