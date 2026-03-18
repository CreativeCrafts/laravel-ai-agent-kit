<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Vector\Exceptions;

use Throwable;

final class VectorOperationException extends VectorStoreException
{
    public static function forOperation(string $operation, string $namespace, ?Throwable $previous = null): self
    {
        return new self(
            message: "Vector store operation [{$operation}] failed for namespace [{$namespace}].",
            previous: $previous,
        );
    }
}
