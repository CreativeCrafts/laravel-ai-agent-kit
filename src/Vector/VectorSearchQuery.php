<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Vector;

use CreativeCrafts\LaravelAiAgentKit\Vector\Exceptions\InvalidVectorQueryException;

final readonly class VectorSearchQuery
{
    /**
     * @param list<float|int> $embedding
     * @param array<string, mixed> $filter
     */
    public function __construct(
        public array $embedding,
        public int $limit = 10,
        public array $filter = [],
    ) {
        if ($this->embedding === []) {
            throw InvalidVectorQueryException::emptyVector();
        }

        if ($this->limit < 1) {
            throw InvalidVectorQueryException::invalidLimit($this->limit);
        }

        foreach ($this->embedding as $index => $value) {
            if (!is_finite((float)$value)) {
                throw InvalidVectorQueryException::nonFiniteValue($index);
            }
        }
    }
}
