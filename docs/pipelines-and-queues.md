# Pipelines and queues

Pipelines run typed steps against a `RunContext`. Use synchronous pipelines for immediate workflows and queued pipelines for long-running work that should carry package budgets, memory state, and result handling.

## Synchronous pipeline

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineRunner;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineStep;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\PipelineBuilder;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;

final class NormalizePipelineService
{
    public function __construct(
        private PipelineRunner $runner,
    ) {
    }

    public function run(): RunContext
    {
        $pipeline = PipelineBuilder::make()
            ->addStep(new class implements PipelineStep
            {
                public function handle(RunContext $context): RunContext
                {
                    return $context
                        ->withStateValue('normalized', true)
                        ->incrementStepCount();
                }
            })
            ->build();

        return $this->runner->run(
            $pipeline,
            new RunContext(
                runId: 'run-001',
                input: ['text' => 'Hello world'],
            ),
        );
    }
}
~~~

## Queued pipeline

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineResultHandler;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineStep;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\QueuedPipelineDefinition;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\QueuedPipelineDispatcher;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Pipeline;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\PipelineBuilder;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\QueueDispatchOptions;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use Throwable;

final class NormalizeTranscriptPipeline implements QueuedPipelineDefinition
{
    public function build(): Pipeline
    {
        return PipelineBuilder::make()
            ->addStep(new class implements PipelineStep
            {
                public function handle(RunContext $context): RunContext
                {
                    return $context
                        ->withStateValue('normalized', true)
                        ->incrementStepCount();
                }
            })
            ->build();
    }
}

final class PersistPipelineResult implements PipelineResultHandler
{
    public function handleSuccess(RunContext $context): void
    {
        // Persist or publish the final pipeline state.
    }

    public function handleFailure(RunContext $context, Throwable $throwable): void
    {
        report($throwable);
    }
}

final class QueueTranscriptNormalizationCommand
{
    public function __construct(
        private QueuedPipelineDispatcher $dispatcher,
    ) {
    }

    public function __invoke(): void
    {
        $this->dispatcher->dispatch(
            pipelineDefinition: NormalizeTranscriptPipeline::class,
            context: new RunContext(
                runId: 'queued-run-001',
                input: ['text' => 'Queued pipeline input'],
            ),
            options: new QueueDispatchOptions(
                queue: 'ai-pipelines',
                connection: 'sync',
            ),
            resultHandler: PersistPipelineResult::class,
        );
    }
}
~~~

## RunContext payload guidance

Queued pipelines serialize the `RunContext`. Keep these fields small and serializable:

| Field | Guidance |
|-------|----------|
| `runId` | Correlation ID for the run. |
| `input` | Explicit associative array; avoid non-serializable objects. |
| `state` | Step state; keep compact. |
| `metadata` | Safe key/value bag; avoid secrets and raw prompt bodies. |
| `conversationId` | Prefer this over serializing a full conversation. |
| `conversation` | Use only when the full graph is truly required. |

## Payload guards

Queued jobs can be rejected before dispatch when the serialized job exceeds `ai-agent-kit.pipeline.queued.max_serialized_job_bytes`.

Use the debug guard for local development:

~~~php
'pipeline' => [
    'queued' => [
        'debug_payload_guard' => true,
        'max_serialized_job_bytes' => 524288,
    ],
],
~~~

`debug_payload_guard` runs only when `app.debug` is true.

Use the production-capable guard when you want dispatch-time protection outside debug mode:

~~~php
'pipeline' => [
    'queued' => [
        'payload_guard' => true,
        'max_serialized_job_bytes' => 524288,
    ],
],
~~~

`payload_guard` is disabled by default. Enable it deliberately after choosing a size that matches your queue backend and workflow payloads.

## When to use Laravel AI SDK jobs directly

Use Agent Kit queued pipelines when you need package budgets, memory, result handlers, and redacted telemetry. Use Laravel AI SDK jobs directly only when you intentionally want the SDK queue contract and do not need the package pipeline envelope.

## Testing queues

Use Laravel queue fakes and package fakes. Do not make live provider calls from queued pipeline tests.

See [Testing](testing.md).
