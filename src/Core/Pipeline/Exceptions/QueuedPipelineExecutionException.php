<?php

namespace CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Exceptions;

use RuntimeException;
use Throwable;

class QueuedPipelineExecutionException extends RuntimeException
{
    public static function forPipeline(string $pipelineDefinition, Throwable $previous): self
    {
        return new self(
            message: "Queued pipeline definition [{$pipelineDefinition}] failed during execution.",
            previous: $previous,
        );
    }
}
