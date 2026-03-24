<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Testing\Fakes;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Vector\VectorStoreInterface;
use CreativeCrafts\LaravelAiAgentKit\Vector\Exceptions\VectorOperationException;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorDocument;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorSearchQuery;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorSearchResult;

final class FakeVectorStore implements VectorStoreInterface
{
    /**
     * @var array<string, array<string, VectorDocument>>
     */
    private array $documents = [];

    /**
     * @var list<array{namespace:string, count:int}>
     */
    private array $upserts = [];

    /**
     * @var list<array{namespace:string, query:VectorSearchQuery}>
     */
    private array $searches = [];

    /**
     * @var list<array{namespace:string, document_ids:list<string>}>
     */
    private array $deletions = [];

    public function __construct(
        private ?string $failingOperation = null,
    ) {
    }

    /**
     * @param list<VectorDocument> $documents
     */
    public function upsert(string $namespace, array $documents): void
    {
        $this->guardOperation('upsert', $namespace);
        $this->upserts[] = [
          'namespace' => $namespace,
          'count' => count($documents),
        ];

        foreach ($documents as $document) {
            $this->documents[$namespace][$document->id] = $document;
        }
    }

    /**
     * @return list<VectorSearchResult>
     */
    public function search(string $namespace, VectorSearchQuery $query): array
    {
        $this->guardOperation('search', $namespace);
        $this->searches[] = [
          'namespace' => $namespace,
          'query' => $query,
        ];

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

    /**
     * @param list<string> $documentIds
     */
    public function delete(string $namespace, array $documentIds): int
    {
        $this->guardOperation('delete', $namespace);
        $this->deletions[] = [
          'namespace' => $namespace,
          'document_ids' => $documentIds,
        ];

        $deleted = 0;

        foreach ($documentIds as $documentId) {
            if (isset($this->documents[$namespace][$documentId])) {
                unset($this->documents[$namespace][$documentId]);
                $deleted++;
            }
        }

        return $deleted;
    }

    public function failOperation(?string $operation): self
    {
        $this->failingOperation = $operation;

        return $this;
    }

    /**
     * @return list<array{namespace:string, count:int}>
     */
    public function upserts(): array
    {
        return $this->upserts;
    }

    /**
     * @return list<array{namespace:string, query:VectorSearchQuery}>
     */
    public function searches(): array
    {
        return $this->searches;
    }

    /**
     * @return list<array{namespace:string, document_ids:list<string>}>
     */
    public function deletions(): array
    {
        return $this->deletions;
    }

    /**
     * @return array<string, VectorDocument>
     */
    public function documents(string $namespace): array
    {
        return $this->documents[$namespace] ?? [];
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
