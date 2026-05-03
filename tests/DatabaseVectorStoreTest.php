<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Vector\VectorStoreInterface;
use CreativeCrafts\LaravelAiAgentKit\Vector\DatabaseVectorStore;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorDocument;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorSearchQuery;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorSearchResult;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('ai_agent_vector_documents');

    /** @var Migration $migration */
    $migration = require __DIR__.'/../database/migrations/create_ai_agent_vector_documents_table.php.stub';
    $migration->up();
});

it('binds the vector store contract to the database adapter', function (): void {
    config()->set('ai-agent-kit.vector.default_driver', 'database');
    config()->set('ai-agent-kit.vector.database.connection', 'testing');
    config()->set('ai-agent-kit.vector.database.table', 'ai_agent_vector_documents');
    forgetResolvedVectorStore();

    expect(app(VectorStoreInterface::class))->toBeInstanceOf(DatabaseVectorStore::class);
});

it('the database adapter complies with vector store contract semantics', function (): void {
    config()->set('ai-agent-kit.vector.default_driver', 'database');
    config()->set('ai-agent-kit.vector.database.connection', 'testing');
    config()->set('ai-agent-kit.vector.database.table', 'ai_agent_vector_documents');
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

it('caps database scan rows when max_scan_rows is set', function (): void {
    config()->set('ai-agent-kit.vector.default_driver', 'database');
    config()->set('ai-agent-kit.vector.database.connection', 'testing');
    config()->set('ai-agent-kit.vector.database.table', 'ai_agent_vector_documents');
    config()->set('ai-agent-kit.vector.database.max_scan_rows', 2);
    forgetResolvedVectorStore();

    /** @var VectorStoreInterface $store */
    $store = app(VectorStoreInterface::class);

    $store->upsert('support', [
        new VectorDocument(
            id: 'doc-1',
            embedding: [1.0, 0.0],
            metadata: [],
        ),
        new VectorDocument(
            id: 'doc-2',
            embedding: [0.0, 1.0],
            metadata: [],
        ),
        new VectorDocument(
            id: 'doc-3',
            embedding: [1.0, 1.0],
            metadata: [],
        ),
    ]);

    $results = $store->search(
        'support',
        new VectorSearchQuery(
            embedding: [1.0, 0.0],
            limit: 10,
            filter: [],
        ),
    );

    expect($results)->toHaveCount(2);
});
