<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Exceptions;

use RuntimeException;

class InvalidQueuedPipelineDefinitionException extends RuntimeException
{
    public static function forClass(string $className): self
    {
        return new self("Queued pipeline definition [{$className}] must implement the queued pipeline definition contract.");
    }
}
