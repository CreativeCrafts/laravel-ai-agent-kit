<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Core;

use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\QueueDispatchOptions;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;

interface QueuedPipelineDispatcher
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
    ): void;
}
