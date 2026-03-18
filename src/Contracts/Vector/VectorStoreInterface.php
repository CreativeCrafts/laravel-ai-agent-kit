<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Vector;

use CreativeCrafts\LaravelAiAgentKit\Vector\VectorDocument;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorSearchQuery;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorSearchResult;

interface VectorStoreInterface
{
    /**
     * @param list<VectorDocument> $documents
     */
    public function upsert(string $namespace, array $documents): void;

    /**
     * @return list<VectorSearchResult>
     */
    public function search(string $namespace, VectorSearchQuery $query): array;

    /**
     * @param list<string> $documentIds
     */
    public function delete(string $namespace, array $documentIds): int;
}
