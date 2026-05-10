<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Vector;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Vector\VectorStoreInterface;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Vector\VectorStoreReferenceEmbedding;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Date;
use JsonException;

final readonly class DatabaseVectorStore implements VectorStoreInterface, VectorStoreReferenceEmbedding
{
    public function __construct(
        private Connection $connection,
        private string $table,
        private ?int $maxScanRows = null,
    ) {
    }

    public function referenceEmbeddingDimensions(string $namespace): ?int
    {
        return $this->firstStoredEmbeddingLength($namespace);
    }

    public function upsert(string $namespace, array $documents): void
    {
        if ($documents === []) {
            return;
        }

        $this->connection->transaction(function () use ($namespace, $documents): void {
            $existing = $this->firstStoredEmbeddingLength($namespace);
            VectorEmbeddingDimensionGuard::assertUpsertBatch($namespace, $existing, $documents);

            $now = Date::now();
            $rows = [];

            foreach ($documents as $document) {
                $rows[] = [
                    'namespace' => $namespace,
                    'document_id' => $document->id,
                    'embedding' => json_encode($document->embedding, JSON_THROW_ON_ERROR),
                    'metadata' => json_encode($document->metadata, JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $this->connection->table($this->table)->upsert(
                $rows,
                ['namespace', 'document_id'],
                ['embedding', 'metadata', 'updated_at'],
            );
        });
    }

    public function search(string $namespace, VectorSearchQuery $query): array
    {
        // When maxScanRows is set, read at most N rows ordered by document_id for stable,
        // deterministic partial scans (top-K is approximate if N < total namespace size).
        $queryBuilder = $this->connection->table($this->table)
            ->where('namespace', $namespace)
            ->orderBy('document_id');

        if ($this->maxScanRows !== null && $this->maxScanRows >= 1) {
            $queryBuilder->limit($this->maxScanRows);
        }

        $rows = $queryBuilder->get(['document_id', 'embedding', 'metadata']);

        $matches = [];

        foreach ($rows as $row) {
            $documentId = $row->document_id ?? null;
            if (!is_string($documentId)) {
                continue;
            }
            if ($documentId === '') {
                continue;
            }

            $embedding = $this->decodeEmbedding($row->embedding);
            if ($embedding === null) {
                continue;
            }

            $metadata = $this->decodeMetadata($row->metadata ?? null);
            $document = new VectorDocument(
                id: $documentId,
                embedding: $embedding,
                metadata: $metadata,
            );

            if (!$this->matchesFilter($document, $query->filter)) {
                continue;
            }

            if (count($query->embedding) !== count($document->embedding)) {
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
        if ($documentIds === []) {
            return 0;
        }

        return $this->connection->table($this->table)
            ->where('namespace', $namespace)
            ->whereIn('document_id', $documentIds)
            ->delete();
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
            $score += (float) $left[$index] * (float) $right[$index];
        }

        return $score;
    }

    /**
     * @return list<float>|null
     */
    private function decodeEmbedding(mixed $value): ?array
    {
        if (is_string($value)) {
            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return null;
            }
        } elseif (is_array($value)) {
            $decoded = $value;
        } else {
            return null;
        }

        if (!is_array($decoded) || $decoded === []) {
            return null;
        }

        $out = [];
        foreach ($decoded as $v) {
            if (!is_int($v) && !is_float($v)) {
                return null;
            }
            $out[] = (float) $v;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMetadata(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return [];
            }
        } elseif (is_array($value)) {
            $decoded = $value;
        } else {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        /** @var array<string, mixed> $assoc */
        $assoc = [];
        foreach ($decoded as $key => $v) {
            $assoc[(string) $key] = $v;
        }

        return $assoc;
    }

    /**
     * @return positive-int|null
     */
    private function firstStoredEmbeddingLength(string $namespace): ?int
    {
        $row = $this->connection->table($this->table)
            ->where('namespace', $namespace)
            ->orderBy('document_id')
            ->first(['embedding']);

        if ($row === null) {
            return null;
        }

        $decoded = $this->decodeEmbedding($row->embedding ?? null);

        if ($decoded === null) {
            return null;
        }

        $length = count($decoded);

        return $length >= 1 ? $length : null;
    }
}
