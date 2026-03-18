<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Vector\Exceptions;

final class InvalidVectorQueryException extends VectorStoreException
{
    public static function emptyVector(): self
    {
        return new self('Vector search queries must contain at least one numeric value.');
    }

    public static function invalidLimit(int $limit): self
    {
        return new self("Vector search limits must be integers >= 1. Received [{$limit}].");
    }

    public static function nonFiniteValue(int $index): self
    {
        return new self("Vector search queries must contain only finite numeric values. Invalid value at index [{$index}].");
    }
}
