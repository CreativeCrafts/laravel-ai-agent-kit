<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Vector;

use CreativeCrafts\LaravelAiAgentKit\Vector\Exceptions\VectorOperationException;

/**
 * Ensures a single embedding width per namespace on upsert batches.
 */
final class VectorEmbeddingDimensionGuard
{
    /**
     * @param list<VectorDocument> $documents
     */
    public static function assertUpsertBatch(string $namespace, ?int $existingNamespaceLength, array $documents): void
    {
        if ($documents === []) {
            return;
        }

        if ($existingNamespaceLength !== null) {
            foreach ($documents as $document) {
                $len = count($document->embedding);
                if ($len !== $existingNamespaceLength) {
                    throw VectorOperationException::forEmbeddingDimensionMismatch(
                        $namespace,
                        $existingNamespaceLength,
                        $len,
                        $document->id,
                    );
                }
            }

            return;
        }

        $expected = count($documents[0]->embedding);

        foreach ($documents as $document) {
            $len = count($document->embedding);
            if ($len !== $expected) {
                throw VectorOperationException::forEmbeddingDimensionMismatch(
                    $namespace,
                    $expected,
                    $len,
                    $document->id,
                );
            }
        }
    }
}
