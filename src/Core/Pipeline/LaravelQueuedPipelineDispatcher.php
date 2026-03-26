<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Pipeline;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineResultHandler;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\QueuedPipelineDefinition;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\QueuedPipelineDispatcher;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Exceptions\InvalidPipelineResultHandlerException;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Exceptions\InvalidQueuedPipelineDefinitionException;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Jobs\RunQueuedPipelineJob;

final class LaravelQueuedPipelineDispatcher implements QueuedPipelineDispatcher
{
    /**
     * @param class-string $pipelineDefinition
     * @param class-string|null $resultHandler
     */
    public function dispatch(
        string $pipelineDefinition,
        RunContext $context,
        QueueDispatchOptions $options = new QueueDispatchOptions(),
        ?string $resultHandler = null,
    ): void {
        if (!class_exists($pipelineDefinition) || !is_subclass_of($pipelineDefinition, QueuedPipelineDefinition::class)) {
            throw InvalidQueuedPipelineDefinitionException::forClass($pipelineDefinition);
        }

        if ($resultHandler !== null && (!class_exists($resultHandler) || !is_subclass_of($resultHandler, PipelineResultHandler::class))) {
            throw InvalidPipelineResultHandlerException::forClass($resultHandler);
        }

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
