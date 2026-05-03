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

    public static function forEmbeddingDimensionMismatch(
        string $namespace,
        int $expectedLength,
        int $actualLength,
        ?string $documentId = null,
    ): self {
        $suffix = $documentId !== null && $documentId !== '' ? " Document [{$documentId}]." : '';

        return new self(
            message: "Vector embedding dimension mismatch for namespace [{$namespace}]: expected length {$expectedLength}, got {$actualLength}.{$suffix}",
        );
    }
}
