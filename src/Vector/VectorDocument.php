<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Vector;

use CreativeCrafts\LaravelAiAgentKit\Vector\Exceptions\InvalidVectorDocumentException;

final readonly class VectorDocument
{
    /**
     * @param list<float|int> $embedding
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $id,
        public array $embedding,
        public array $metadata = [],
    ) {
        if ($this->id === '') {
            throw InvalidVectorDocumentException::emptyId();
        }

        if ($this->embedding === []) {
            throw InvalidVectorDocumentException::emptyVector();
        }

        foreach ($this->embedding as $index => $value) {
            if (!is_finite((float)$value)) {
                throw InvalidVectorDocumentException::nonFiniteValue($index);
            }
        }
    }
}
