<?php

namespace CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Exceptions;

use RuntimeException;
use Throwable;

class PipelineExecutionException extends RuntimeException
{
    public static function forStep(string $stepClass, Throwable $previous): self
    {
        return new self(
            message: "Pipeline step [{$stepClass}] failed during synchronous execution.",
            previous: $previous,
        );
    }
}
