<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineRunner;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\QueuedPipelineDispatcher;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Exceptions\InvalidPipelineResultHandlerException;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Exceptions\InvalidQueuedPipelineDefinitionException;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Exceptions\QueuedPipelineExecutionException;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Jobs\RunQueuedPipelineJob;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\LaravelQueuedPipelineDispatcher;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\QueueDispatchOptions;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fakes\FailingQueuedPipelineDefinition;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fakes\TestPipelineResultHandler;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fakes\TestQueuedPipelineDefinition;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

beforeEach(function () {
    TestPipelineResultHandler::reset();

    app()->singleton(TestQueuedPipelineDefinition::class, fn (): TestQueuedPipelineDefinition => new TestQueuedPipelineDefinition());
    app()->singleton(FailingQueuedPipelineDefinition::class, fn (): FailingQueuedPipelineDefinition => new FailingQueuedPipelineDefinition());
    app()->singleton(TestPipelineResultHandler::class, fn (): TestPipelineResultHandler => new TestPipelineResultHandler());
});

it('binds the queued pipeline dispatcher contract', function () {
    $dispatcher = app(QueuedPipelineDispatcher::class);

    expect($dispatcher)->toBeInstanceOf(LaravelQueuedPipelineDispatcher::class);
});

it('dispatches a queued pipeline job with typed payload and queue options', function () {
    Queue::fake();

    $dispatcher = app(QueuedPipelineDispatcher::class);

    $dispatcher->dispatch(
        pipelineDefinition: TestQueuedPipelineDefinition::class,
        context: new RunContext(runId: 'run-queued', input: ['text' => 'hello']),
        options: new QueueDispatchOptions(
            connection: 'redis',
            queue: 'ai-pipelines',
            delaySeconds: 15,
            timeoutSeconds: 90,
        ),
        resultHandler: TestPipelineResultHandler::class,
    );

    Queue::assertPushed(RunQueuedPipelineJob::class, function (RunQueuedPipelineJob $job): bool {
        return $job->pipelineDefinition() === TestQueuedPipelineDefinition::class
          && $job->context()->runId === 'run-queued'
          && $job->context()->inputValue('text') === 'hello'
          && $job->resultHandler() === TestPipelineResultHandler::class
          && $job->connection === 'redis'
          && $job->queue === 'ai-pipelines'
          && $job->delay === 15
          && $job->timeout === 90;
    });
});

it('executes a queued pipeline job and forwards successful results explicitly', function () {
    $job = new RunQueuedPipelineJob(
        pipelineDefinition: TestQueuedPipelineDefinition::class,
        context: new RunContext(runId: 'run-success', input: ['text' => 'hello']),
        resultHandler: TestPipelineResultHandler::class,
        options: new QueueDispatchOptions(queue: 'ai-pipelines', timeoutSeconds: 60),
    );

    $job->handle(
        runner: app(PipelineRunner::class),
        app: app(Application::class),
    );

    expect(TestPipelineResultHandler::$successes)
      ->toHaveCount(1)
      ->and(TestPipelineResultHandler::$successes[0]->runId)->toBe('run-success')
      ->and(TestPipelineResultHandler::$successes[0]->stateValue('queued'))->toBeTrue()
      ->and(TestPipelineResultHandler::$successes[0]->stepCount)->toBe(1)
      ->and(TestPipelineResultHandler::$failures)->toBe([]);
});

it('wraps queued pipeline failures and preserves the previous exception chain', function () {
    $job = new RunQueuedPipelineJob(
        pipelineDefinition: FailingQueuedPipelineDefinition::class,
        context: new RunContext(runId: 'run-failure'),
        resultHandler: TestPipelineResultHandler::class,
    );

    try {
        $job->handle(
            runner: app(PipelineRunner::class),
            app: app(Application::class),
        );

        $this->fail('Expected queued pipeline execution to fail.');
    } catch (QueuedPipelineExecutionException $exception) {
        expect($exception->getPrevious())->not
          ->toBeNull()
          ->and($exception->getPrevious()?->getMessage())->toContain('failed during synchronous execution')
          ->and(TestPipelineResultHandler::$successes)->toBe([])
          ->and(TestPipelineResultHandler::$failures)->toHaveCount(1)
          ->and(TestPipelineResultHandler::$failures[0]['context']->runId)->toBe('run-failure')
          ->and(TestPipelineResultHandler::$failures[0]['throwable']->getMessage())->toContain('failed during synchronous execution');
    }
});

it('preserves the original queued pipeline throwable when the result handler failure callback also fails', function () {
    TestPipelineResultHandler::$throwOnFailure = true;

    $job = new RunQueuedPipelineJob(
        pipelineDefinition: FailingQueuedPipelineDefinition::class,
        context: new RunContext(runId: 'run-failure-handler-throws'),
        resultHandler: TestPipelineResultHandler::class,
    );

    $job->handle(
        runner: app(PipelineRunner::class),
        app: app(Application::class),
    );
})->throws(QueuedPipelineExecutionException::class, 'failed during execution');

it('fails synchronously when dispatching an invalid queued pipeline definition class', function () {
    /** @var QueuedPipelineDispatcher $dispatcher */
    $dispatcher = app(QueuedPipelineDispatcher::class);

    $dispatcher->dispatch(
        pipelineDefinition: TestPipelineResultHandler::class,
        context: new RunContext(runId: 'run-invalid-definition'),
    );
})->throws(InvalidQueuedPipelineDefinitionException::class);

it('throws when debug payload guard rejects an oversized queued job', function () {
    config()->set('app.debug', true);
    config()->set('ai-agent-kit.pipeline.queued.debug_payload_guard', true);
    config()->set('ai-agent-kit.pipeline.queued.max_serialized_job_bytes', 256);

    /** @var QueuedPipelineDispatcher $dispatcher */
    $dispatcher = app(QueuedPipelineDispatcher::class);

    $large = str_repeat('x', 400);
    $dispatcher->dispatch(
        pipelineDefinition: TestQueuedPipelineDefinition::class,
        context: new RunContext(runId: 'run-big', input: ['blob' => $large]),
    );
})->throws(RuntimeException::class);

it('fails synchronously when dispatching an invalid result handler class', function () {
    /** @var QueuedPipelineDispatcher $dispatcher */
    $dispatcher = app(QueuedPipelineDispatcher::class);

    $dispatcher->dispatch(
        pipelineDefinition: TestQueuedPipelineDefinition::class,
        context: new RunContext(runId: 'run-invalid-handler'),
        resultHandler: TestQueuedPipelineDefinition::class,
    );
})->throws(InvalidPipelineResultHandlerException::class);
