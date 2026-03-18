<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Vector\VectorStoreInterface;
use CreativeCrafts\LaravelAiAgentKit\Tests\Fakes\InMemoryVectorStoreFake;
use CreativeCrafts\LaravelAiAgentKit\Vector\Exceptions\InvalidVectorDocumentException;
use CreativeCrafts\LaravelAiAgentKit\Vector\Exceptions\InvalidVectorQueryException;
use CreativeCrafts\LaravelAiAgentKit\Vector\Exceptions\VectorOperationException;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorDocument;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorSearchQuery;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorSearchResult;

it('supports upsert search and delete semantics through the vector store contract', function () {
    /** @var VectorStoreInterface $store */
    $store = new InMemoryVectorStoreFake();

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

it('replaces documents with the same id on upsert', function () {
    /** @var VectorStoreInterface $store */
    $store = new InMemoryVectorStoreFake();

    $store->upsert('support', [
      new VectorDocument(id: 'doc-1', embedding: [0.1, 0.1], metadata: ['version' => 1]),
    ]);
    $store->upsert('support', [
      new VectorDocument(id: 'doc-1', embedding: [1.0, 1.0], metadata: ['version' => 2]),
    ]);

    $results = $store->search(
        'support',
        new VectorSearchQuery(
            embedding: [1.0, 1.0],
            limit: 1,
        ),
    );

    expect($results)
      ->toHaveCount(1)
      ->and($results[0]->id)->toBe('doc-1')
      ->and($results[0]->metadata)->toBe(['version' => 2]);
});

it('throws typed exceptions for invalid vector documents and queries', function () {
    expect(fn (): VectorDocument => new VectorDocument(id: '', embedding: [1.0]))
      ->toThrow(InvalidVectorDocumentException::class)
      ->and(fn (): VectorDocument => new VectorDocument(id: 'doc-1', embedding: []))
      ->toThrow(InvalidVectorDocumentException::class)
      ->and(fn (): VectorSearchQuery => new VectorSearchQuery(embedding: [], limit: 5))
      ->toThrow(InvalidVectorQueryException::class)
      ->and(fn (): VectorSearchQuery => new VectorSearchQuery(embedding: [1.0], limit: 0))
      ->toThrow(InvalidVectorQueryException::class);
});

it('throws a typed vector operation exception when a backend operation fails', function () {
    /** @var VectorStoreInterface $store */
    $store = new InMemoryVectorStoreFake('search');

    $store->upsert('support', [
      new VectorDocument(id: 'doc-1', embedding: [1.0, 0.0]),
    ]);

    expect(fn (): array => $store->search('support', new VectorSearchQuery(embedding: [1.0, 0.0])))
      ->toThrow(VectorOperationException::class);
});
