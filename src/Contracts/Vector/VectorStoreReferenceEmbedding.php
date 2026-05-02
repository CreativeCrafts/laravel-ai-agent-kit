<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Vector;

use CreativeCrafts\LaravelAiAgentKit\Tools\SimilaritySearchTool;

/**
 * Optional capability: report the embedding width of stored documents in a namespace
 * (for tools such as {@see SimilaritySearchTool}).
 */
interface VectorStoreReferenceEmbedding
{
    /**
     * Return the vector length of an arbitrary stored document in the namespace, or null if empty.
     * Implementations SHOULD use a stable ordering (e.g. minimum document id) when sampling.
     */
    public function referenceEmbeddingDimensions(string $namespace): ?int;
}
