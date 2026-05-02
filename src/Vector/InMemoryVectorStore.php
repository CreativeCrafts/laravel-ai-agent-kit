<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Vector;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Vector\VectorStoreInterface;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Vector\VectorStoreReferenceEmbedding;

final class InMemoryVectorStore implements VectorStoreInterface, VectorStoreReferenceEmbedding
{
    /** @var array<string, array<string, VectorDocument>> */
    private array $documents = [];

    public function upsert(string $namespace, array $documents): void
    {
        foreach ($documents as $document) {
            $this->documents[$namespace][$document->id] = $document;
        }
    }

    public function referenceEmbeddingDimensions(string $namespace): ?int
    {
        $docs = $this->documents[$namespace] ?? [];
        if ($docs === []) {
            return null;
        }

        ksort($docs);
        $first = reset($docs);

        return count($first->embedding);
    }

    public function search(string $namespace, VectorSearchQuery $query): array
    {
        $matches = [];

        foreach ($this->documents[$namespace] ?? [] as $document) {
            if (!$this->matchesFilter($document, $query->filter)) {
                continue;
            }

            $matches[] = new VectorSearchResult(
                id: $document->id,
                score: $this->score($query->embedding, $document->embedding),
                metadata: $document->metadata,
            );
        }

        usort($matches, static function (VectorSearchResult $left, VectorSearchResult $right): int {
            return $right->score <=> $left->score;
        });

        return array_slice($matches, 0, $query->limit);
    }

    public function delete(string $namespace, array $documentIds): int
    {
        $deleted = 0;

        foreach ($documentIds as $documentId) {
            if (isset($this->documents[$namespace][$documentId])) {
                unset($this->documents[$namespace][$documentId]);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * @param array<string, mixed> $filter
     */
    private function matchesFilter(VectorDocument $document, array $filter): bool
    {
        foreach ($filter as $key => $value) {
            if (($document->metadata[$key] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<float|int> $left
     * @param list<float|int> $right
     */
    private function score(array $left, array $right): float
    {
        $limit = min(count($left), count($right));
        $score = 0.0;

        for ($index = 0; $index < $limit; $index++) {
            $score += (float)$left[$index] * (float)$right[$index];
        }

        return $score;
    }
}
