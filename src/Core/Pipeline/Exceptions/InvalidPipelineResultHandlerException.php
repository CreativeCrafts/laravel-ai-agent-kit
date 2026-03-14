<?php

namespace CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Exceptions;

use RuntimeException;

class InvalidPipelineResultHandlerException extends RuntimeException
{
    public static function forClass(string $className): self
    {
        return new self("Pipeline result handler [{$className}] must implement the pipeline result handler contract.");
    }
}
