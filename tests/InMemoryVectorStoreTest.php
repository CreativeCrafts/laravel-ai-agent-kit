<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Vector\VectorStoreInterface;
use CreativeCrafts\LaravelAiAgentKit\Vector\InMemoryVectorStore;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorDocument;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorSearchQuery;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorSearchResult;

it('binds the vector store contract to the in-memory adapter', function () {
    config()->set('ai-agent-kit.vector.default_driver', 'in_memory');
    forgetResolvedVectorStore();

    expect(app(VectorStoreInterface::class))->toBeInstanceOf(InMemoryVectorStore::class);
});

it('the in-memory adapter complies with vector store contract semantics', function () {
    config()->set('ai-agent-kit.vector.default_driver', 'in_memory');
    forgetResolvedVectorStore();

    /** @var VectorStoreInterface $store */
    $store = app(VectorStoreInterface::class);

    $store->upsert('support', [
      new VectorDocument(
          id: 'doc-1',
          embedding: [1.0, 0.0],
          metadata: ['topic' => 'billing'],
      ),
      new VectorDocument(
          id: 'doc-2',
          embedding: [0.5, 0.5],
          metadata: ['topic' => 'support'],
      ),
      new VectorDocument(
          id: 'doc-3',
          embedding: [0.0, 1.0],
          metadata: ['topic' => 'billing'],
      ),
    ]);

    $results = $store->search(
        'support',
        new VectorSearchQuery(
            embedding: [1.0, 0.0],
            limit: 2,
            filter: ['topic' => 'billing'],
        ),
    );

    expect($results)
      ->toHaveCount(2)
      ->and($results[0])->toBeInstanceOf(VectorSearchResult::class)
      ->and($results[0]->id)->toBe('doc-1')
      ->and($results[1]->id)->toBe('doc-3');

    $store->upsert('support', [
      new VectorDocument(
          id: 'doc-1',
          embedding: [1.0, 1.0],
          metadata: ['topic' => 'billing', 'version' => 2],
      ),
    ]);

    $replacement = $store->search(
        'support',
        new VectorSearchQuery(
            embedding: [1.0, 1.0],
            limit: 1,
            filter: ['topic' => 'billing'],
        ),
    );

    expect($replacement)
      ->toHaveCount(1)
      ->and($replacement[0]->id)->toBe('doc-1')
      ->and($replacement[0]->metadata)->toBe(['topic' => 'billing', 'version' => 2]);

    $deleted = $store->delete('support', ['doc-1', 'missing-doc']);
    $afterDelete = $store->search(
        'support',
        new VectorSearchQuery(
            embedding: [1.0, 0.0],
            limit: 5,
        ),
    );

    expect($deleted)
      ->toBe(1)
      ->and(array_map(static fn (VectorSearchResult $result): string => $result->id, $afterDelete))
      ->toBe(['doc-2', 'doc-3']);
});

function forgetResolvedVectorStore(): void
{
    app()->forgetInstance(VectorStoreInterface::class);
    app()->forgetInstance(InMemoryVectorStore::class);
}
