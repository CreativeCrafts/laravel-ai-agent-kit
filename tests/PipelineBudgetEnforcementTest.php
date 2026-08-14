<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineRunner;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineStep;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Resilience\Sleeper;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Exceptions\PipelineBudgetExceededException;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Exceptions\PipelineExecutionException;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\PipelineBuilder;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\SynchronousPipelineRunner;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeSleeper;
use Illuminate\Http\Client\ConnectionException;

it('fails with a typed exception when the pipeline exceeds max_steps budget', function () {
    config()->set('ai-agent-kit.budgets.max_steps', 1);
    forgetResolvedPipelineRunners();

    $pipeline = PipelineBuilder::make()
      ->addStep(
          new class () implements PipelineStep {
            public function handle(RunContext $context): RunContext
            {
                return $context->incrementStepCount();
            }
        },
      )
      ->addStep(
          new class () implements PipelineStep {
            public function handle(RunContext $context): RunContext
            {
                return $context->incrementStepCount();
            }
        },
      )
      ->build();

    /** @var PipelineRunner $runner */
    $runner = app(PipelineRunner::class);

    expect(fn (): RunContext => $runner->run($pipeline, new RunContext(runId: 'run-budget-max-steps')))
      ->toThrow(PipelineBudgetExceededException::class, 'max_steps');
});

it('fails with a typed exception when total timeout budget is exceeded', function () {
    config()->set('ai-agent-kit.budgets.max_total_timeout_seconds', 1);
    forgetResolvedPipelineRunners();

    $pipeline = PipelineBuilder::make()
      ->addStep(
          new class () implements PipelineStep {
            public function handle(RunContext $context): RunContext
            {
                usleep(1_500_000);

                return $context->incrementStepCount();
            }
        },
      )
      ->build();

    /** @var PipelineRunner $runner */
    $runner = app(PipelineRunner::class);

    expect(fn (): RunContext => $runner->run($pipeline, new RunContext(runId: 'run-budget-timeout')))
      ->toThrow(PipelineBudgetExceededException::class, 'max_total_timeout_seconds');
});

it('retries failed pipeline steps according to the configured retry policy', function () {
    config()->set('ai-agent-kit.budgets.max_retries_per_step', 2);
    config()->set('ai-agent-kit.resilience.retry', [
      'enabled' => true,
      'max_attempts' => 3,
      'backoff' => [
        'strategy' => 'constant',
        'base_delay_ms' => 0,
        'max_delay_ms' => 0,
        'multiplier' => 1.0,
      ],
    ]);
    forgetResolvedPipelineRunners();

    $attempts = 0;

    $pipeline = PipelineBuilder::make()
      ->addStep(
          new class ($attempts) implements PipelineStep {
            public function __construct(private int &$attempts)
            {
            }

            public function handle(RunContext $context): RunContext
            {
                $this->attempts++;

                if ($this->attempts < 3) {
                    throw new ConnectionException('retry me');
                }

                return $context
                  ->withStateValue('attempts', $this->attempts)
                  ->incrementStepCount();
            }
        },
      )
      ->build();

    /** @var PipelineRunner $runner */
    $runner = app(PipelineRunner::class);
    $result = $runner->run($pipeline, new RunContext(runId: 'run-retry-success'));

    expect($result->stateValue('attempts'))
      ->toBe(3)
      ->and($result->stepCount)->toBe(1);
});

it('stops retrying when max attempts are exhausted and wraps the failure', function () {
    config()->set('ai-agent-kit.budgets.max_retries_per_step', 1);
    config()->set('ai-agent-kit.resilience.retry', [
      'enabled' => true,
      'max_attempts' => 10,
      'backoff' => [
        'strategy' => 'constant',
        'base_delay_ms' => 0,
        'max_delay_ms' => 0,
        'multiplier' => 1.0,
      ],
    ]);
    forgetResolvedPipelineRunners();

    $attempts = 0;

    $pipeline = PipelineBuilder::make()
      ->addStep(
          new class ($attempts) implements PipelineStep {
            public function __construct(private int &$attempts)
            {
            }

            public function handle(RunContext $context): RunContext
            {
                $this->attempts++;

                throw new ConnectionException('still failing');
            }
        },
      )
      ->build();

    /** @var PipelineRunner $runner */
    $runner = app(PipelineRunner::class);

    expect(fn (): RunContext => $runner->run($pipeline, new RunContext(runId: 'run-retry-failure')))
      ->toThrow(PipelineExecutionException::class, 'failed during synchronous execution')
      ->and($attempts)->toBe(2);
});

it('does not retry deterministic invalid pipeline step input', function (): void {
    config()->set('ai-agent-kit.budgets.max_retries_per_step', 3);
    config()->set('ai-agent-kit.resilience.retry', [
      'enabled' => true,
      'max_attempts' => 4,
      'backoff' => [
        'strategy' => 'constant',
        'base_delay_ms' => 0,
        'max_delay_ms' => 0,
        'multiplier' => 1.0,
      ],
    ]);
    forgetResolvedPipelineRunners();

    $attempts = 0;
    $pipeline = PipelineBuilder::make()
      ->addStep(new class ($attempts) implements PipelineStep {
          public function __construct(private int &$attempts)
          {
          }

          public function handle(RunContext $context): RunContext
          {
              $this->attempts++;

              throw new InvalidArgumentException('deterministic invalid input');
          }
      })
      ->build();

    /** @var PipelineRunner $runner */
    $runner = app(PipelineRunner::class);

    expect(fn (): RunContext => $runner->run($pipeline, new RunContext(runId: 'run-no-deterministic-retry')))
        ->toThrow(PipelineExecutionException::class)
        ->and($attempts)->toBe(1);
});

it('uses an injectable sleeper for retry backoff without waiting in tests', function (): void {
    config()->set('ai-agent-kit.budgets.max_retries_per_step', 1);
    config()->set('ai-agent-kit.resilience.retry', [
      'enabled' => true,
      'max_attempts' => 2,
      'backoff' => [
        'strategy' => 'constant',
        'base_delay_ms' => 250,
        'max_delay_ms' => 250,
        'multiplier' => 1.0,
      ],
    ]);
    $sleeper = new FakeSleeper();
    app()->instance(Sleeper::class, $sleeper);
    forgetResolvedPipelineRunners();

    $attempts = 0;
    $pipeline = PipelineBuilder::make()
      ->addStep(new class ($attempts) implements PipelineStep {
          public function __construct(private int &$attempts)
          {
          }

          public function handle(RunContext $context): RunContext
          {
              $this->attempts++;

              if ($this->attempts === 1) {
                  throw new ConnectionException('retry after backoff');
              }

              return $context->incrementStepCount();
          }
      })
      ->build();

    /** @var PipelineRunner $runner */
    $runner = app(PipelineRunner::class);
    $runner->run($pipeline, new RunContext(runId: 'run-fake-sleeper'));

    expect($attempts)->toBe(2)
        ->and($sleeper->recordedDelays())->toBe([250]);
});

it('rejects retry backoff that cannot fit in the remaining timeout before sleeping', function (): void {
    config()->set('ai-agent-kit.budgets.max_total_timeout_seconds', 1);
    config()->set('ai-agent-kit.budgets.max_retries_per_step', 1);
    config()->set('ai-agent-kit.resilience.retry', [
      'enabled' => true,
      'max_attempts' => 2,
      'backoff' => [
        'strategy' => 'constant',
        'base_delay_ms' => 1500,
        'max_delay_ms' => 1500,
        'multiplier' => 1.0,
      ],
    ]);
    $sleeper = new FakeSleeper();
    app()->instance(Sleeper::class, $sleeper);
    forgetResolvedPipelineRunners();

    $pipeline = PipelineBuilder::make()
      ->addStep(new class () implements PipelineStep {
          public function handle(RunContext $context): RunContext
          {
              throw new ConnectionException('retry would exceed timeout');
          }
      })
      ->build();

    /** @var PipelineRunner $runner */
    $runner = app(PipelineRunner::class);

    expect(fn (): RunContext => $runner->run($pipeline, new RunContext(runId: 'run-delay-budget')))
        ->toThrow(PipelineBudgetExceededException::class, 'would exceed max_total_timeout_seconds')
        ->and($sleeper->recordedDelays())->toBe([]);
});

function forgetResolvedPipelineRunners(): void
{
    app()->forgetInstance(PipelineRunner::class);
    app()->forgetInstance(SynchronousPipelineRunner::class);
}
