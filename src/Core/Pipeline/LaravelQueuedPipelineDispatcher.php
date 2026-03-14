<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Pipeline;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineResultHandler;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\QueuedPipelineDefinition;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\QueuedPipelineDispatcher;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Jobs\RunQueuedPipelineJob;

final class LaravelQueuedPipelineDispatcher implements QueuedPipelineDispatcher
{
    /**
     * @param  class-string<QueuedPipelineDefinition>  $pipelineDefinition
     * @param  class-string<PipelineResultHandler>|null  $resultHandler
     */
    public function dispatch(
        string $pipelineDefinition,
        RunContext $context,
        QueueDispatchOptions $options = new QueueDispatchOptions(),
        ?string $resultHandler = null,
    ): void {
        dispatch(
            new RunQueuedPipelineJob(
                pipelineDefinition: $pipelineDefinition,
                context: $context,
                resultHandler: $resultHandler,
                options: $options,
            ),
        );
    }
}
