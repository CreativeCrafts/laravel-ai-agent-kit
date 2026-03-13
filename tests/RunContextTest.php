<?php

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineStep;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;

it('constructs a run context with explicit orchestration state', function () {
    $context = new RunContext(
        runId: 'run-123',
        input: ['prompt' => 'Summarize this transcript'],
        state: ['transcript' => 'Initial transcript'],
        metadata: ['source' => 'upload'],
        stepCount: 1,
        toolCallCount: 2,
        selectedProvider: 'null',
    );

    expect($context->runId)
        ->toBe('run-123')
        ->and($context->input)->toBe(['prompt' => 'Summarize this transcript'])
        ->and($context->state)->toBe(['transcript' => 'Initial transcript'])
        ->and($context->metadata)->toBe(['source' => 'upload'])
        ->and($context->stepCount)->toBe(1)
        ->and($context->toolCallCount)->toBe(2)
        ->and($context->selectedProvider)->toBe('null')
        ->and($context->hasInputValue('prompt'))->toBeTrue()
        ->and($context->inputValue('prompt'))->toBe('Summarize this transcript')
        ->and($context->hasStateValue('transcript'))->toBeTrue()
        ->and($context->stateValue('transcript'))->toBe('Initial transcript')
        ->and($context->metadataValue('source'))->toBe('upload');
});

it('returns a new run context when stateful orchestration values change', function () {
    $context = new RunContext(
        runId: 'run-immutable',
        input: ['prompt' => 'Analyse'],
    );

    $updated = $context
        ->withStateValue('analysis', 'done')
        ->withMetadataValue('queued', false)
        ->withSelectedProvider('null')
        ->incrementStepCount()
        ->incrementToolCallCount(2);

    expect($context->state)
        ->toBe([])
        ->and($context->metadata)->toBe([])
        ->and($context->selectedProvider)->toBeNull()
        ->and($context->stepCount)->toBe(0)
        ->and($context->toolCallCount)->toBe(0)
        ->and($updated)->not
        ->toBe($context)
        ->and($updated->stateValue('analysis'))->toBe('done')
        ->and($updated->metadataValue('queued'))->toBeFalse()
        ->and($updated->selectedProvider)->toBe('null')
        ->and($updated->stepCount)->toBe(1)
        ->and($updated->toolCallCount)->toBe(2);
});

it('allows pipeline steps to transform a run context deterministically', function () {
    $step = new class implements PipelineStep
    {
        public function handle(RunContext $context): RunContext
        {
            return $context
                ->withStateValue('transcript', 'Normalized transcript')
                ->incrementStepCount();
        }
    };

    $context = new RunContext(
        runId: 'run-step',
        input: ['audio' => 'clip.wav'],
    );

    $result = $step->handle($context);

    expect($result)
        ->toBeInstanceOf(RunContext::class)
        ->and($result->stateValue('transcript'))->toBe('Normalized transcript')
        ->and($result->stepCount)->toBe(1)
        ->and($context->state)->toBe([])
        ->and($context->stepCount)->toBe(0);
});
