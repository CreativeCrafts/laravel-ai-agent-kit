<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fakes;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Vector\VectorStoreInterface;
use CreativeCrafts\LaravelAiAgentKit\Vector\Exceptions\VectorOperationException;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorDocument;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorSearchQuery;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorSearchResult;

final class InMemoryVectorStoreFake implements VectorStoreInterface
{
    /** @var array<string, array<string, VectorDocument>> */
    private array $documents = [];

    public function __construct(
        private readonly ?string $failingOperation = null,
    ) {
    }

    public function upsert(string $namespace, array $documents): void
    {
        $this->guardOperation('upsert', $namespace);

        foreach ($documents as $document) {
            $this->documents[$namespace][$document->id] = $document;
        }
    }

    public function search(string $namespace, VectorSearchQuery $query): array
    {
        $this->guardOperation('search', $namespace);

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
        $this->guardOperation('delete', $namespace);

        $deleted = 0;

        foreach ($documentIds as $documentId) {
            if (isset($this->documents[$namespace][$documentId])) {
                unset($this->documents[$namespace][$documentId]);
                $deleted++;
            }
        }

        return $deleted;
    }

    private function guardOperation(string $operation, string $namespace): void
    {
        if ($this->failingOperation === $operation) {
            throw VectorOperationException::forOperation($operation, $namespace);
        }
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
