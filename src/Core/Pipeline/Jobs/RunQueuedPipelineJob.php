<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Jobs;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineResultHandler;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineRunner;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\QueuedPipelineDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Exceptions\InvalidPipelineResultHandlerException;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Exceptions\InvalidQueuedPipelineDefinitionException;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Exceptions\QueuedPipelineExecutionException;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\QueueDispatchOptions;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunQueuedPipelineJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public ?int $timeout = null;

    public ?int $tries = null;

    public ?int $maxExceptions = null;

    public ?int $backoff = null;

    /**
     * @param class-string<QueuedPipelineDefinition> $pipelineDefinition
     * @param class-string<PipelineResultHandler>|null $resultHandler
     */
    public function __construct(
        public readonly string $pipelineDefinition,
        public readonly RunContext $context,
        public readonly ?string $resultHandler = null,
        QueueDispatchOptions $options = new QueueDispatchOptions(),
    ) {
        if ($options->connection !== null) {
            $this->onConnection($options->connection);
        }

        if ($options->queue !== null) {
            $this->onQueue($options->queue);
        }

        if ($options->delaySeconds !== null) {
            $this->delay = $options->delaySeconds;
        }

        $this->timeout = $options->timeoutSeconds;
        $this->tries = $options->tries;
        $this->maxExceptions = $options->maxExceptions;
        $this->backoff = $options->backoffSeconds;
    }

    /**
     * @throws BindingResolutionException
     */
    public function handle(PipelineRunner $runner, Application $app): void
    {
        $definition = $app->make($this->pipelineDefinition);

        if (!$definition instanceof QueuedPipelineDefinition) {
            throw InvalidQueuedPipelineDefinitionException::forClass($this->pipelineDefinition);
        }

        $resultHandler = $this->resolveResultHandler($app);

        try {
            $result = $runner->run($definition->build(), $this->context);

            $resultHandler?->handleSuccess($result);
        } catch (Throwable $throwable) {
            if ($resultHandler instanceof PipelineResultHandler) {
                try {
                    $resultHandler->handleFailure($this->context, $throwable);
                } catch (Throwable) {
                    // Preserve the original pipeline failure as the canonical cause.
                }
            }

            throw QueuedPipelineExecutionException::forPipeline($this->pipelineDefinition, $throwable);
        }
    }

    public function pipelineDefinition(): string
    {
        return $this->pipelineDefinition;
    }

    public function context(): RunContext
    {
        return $this->context;
    }

    public function resultHandler(): ?string
    {
        return $this->resultHandler;
    }

    private function resolveResultHandler(Application $app): ?PipelineResultHandler
    {
        if ($this->resultHandler === null) {
            return null;
        }

        $resultHandler = $app->make($this->resultHandler);

        if (!$resultHandler instanceof PipelineResultHandler) {
            throw InvalidPipelineResultHandlerException::forClass($this->resultHandler);
        }

        return $resultHandler;
    }
}
