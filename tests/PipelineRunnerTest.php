<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineRunner;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineStep;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Exceptions\PipelineExecutionException;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\PipelineBuilder;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\SynchronousPipelineRunner;

it('binds the synchronous pipeline runner contract', function () {
    $runner = app(PipelineRunner::class);

    expect($runner)->toBeInstanceOf(SynchronousPipelineRunner::class);
});

it('builds a pipeline fluently and runs steps in order', function () {
    $pipeline = PipelineBuilder::make()
        ->addStep(
            new class () implements PipelineStep {
                public function handle(RunContext $context): RunContext
                {
                    $log = $context->stateValue('log', []);
                    $log[] = 'first';

                    return $context
                        ->withStateValue('log', $log)
                        ->withStateValue('transcript', 'Normalized transcript')
                        ->incrementStepCount();
                }
            },
        )
        ->addSteps([
            new class () implements PipelineStep {
                public function handle(RunContext $context): RunContext
                {
                    $log = $context->stateValue('log', []);
                    $log[] = 'second';

                    return $context
                        ->withStateValue('log', $log)
                        ->withMetadataValue('result', 'complete')
                        ->incrementStepCount();
                }
            },
        ])
        ->build();

    $runner = app(PipelineRunner::class);
    $initialContext = new RunContext(
        runId: 'run-pipeline',
        input: ['audio' => 'clip.wav'],
    );

    $result = $runner->run($pipeline, $initialContext);

    expect($pipeline->steps())
        ->toHaveCount(2)
        ->and($result->stateValue('log'))->toBe(['first', 'second'])
        ->and($result->stateValue('transcript'))->toBe('Normalized transcript')
        ->and($result->metadataValue('result'))->toBe('complete')
        ->and($result->stepCount)->toBe(2)
        ->and($initialContext->state)->toBe([])
        ->and($initialContext->stepCount)->toBe(0);
});

it('wraps step failures in a typed pipeline execution exception', function () {
    $pipeline = PipelineBuilder::make()
        ->addStep(
            new class () implements PipelineStep {
                public function handle(RunContext $context): RunContext
                {
                    throw new RuntimeException('Step failure');
                }
            },
        )
        ->build();

    $runner = new SynchronousPipelineRunner();

    expect(fn (): RunContext => $runner->run($pipeline, new RunContext(runId: 'run-failure')))
        ->toThrow(PipelineExecutionException::class, 'failed during synchronous execution');
});
