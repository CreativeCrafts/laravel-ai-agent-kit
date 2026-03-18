<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Vector\Exceptions;

final class InvalidVectorDocumentException extends VectorStoreException
{
    public static function emptyId(): self
    {
        return new self('Vector document ids must be non-empty strings.');
    }

    public static function emptyVector(): self
    {
        return new self('Vector document embeddings must contain at least one numeric value.');
    }

    public static function nonFiniteValue(int $index): self
    {
        return new self("Vector document embeddings must contain only finite numeric values. Invalid value at index [{$index}].");
    }
}
