<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Vector\VectorStoreInterface;
use CreativeCrafts\LaravelAiAgentKit\Testing\Fakes\FakeVectorStore;
use CreativeCrafts\LaravelAiAgentKit\Vector\DatabaseVectorStore;
use CreativeCrafts\LaravelAiAgentKit\Vector\Exceptions\VectorOperationException;
use CreativeCrafts\LaravelAiAgentKit\Vector\InMemoryVectorStore;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorDocument;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorSearchQuery;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('rejects upsert when batch mixes embedding lengths in an empty namespace', function (): void {
    $store = new InMemoryVectorStore();

    expect(fn () => $store->upsert('ns', [
        new VectorDocument('a', [1.0, 0.0]),
        new VectorDocument('b', [1.0, 0.0, 0.0]),
    ]))->toThrow(VectorOperationException::class);
});

it('rejects upsert when embedding length disagrees with existing namespace', function (): void {
    $store = new InMemoryVectorStore();
    $store->upsert('ns', [new VectorDocument('a', [1.0, 0.0])]);

    expect(fn () => $store->upsert('ns', [new VectorDocument('b', [1.0])]))
        ->toThrow(VectorOperationException::class);
});

it('skips documents whose embedding length differs from the query vector', function (): void {
    $store = new InMemoryVectorStore();
    $store->upsert('ns', [
        new VectorDocument('two', [1.0, 0.0]),
    ]);

    $ref = new ReflectionClass($store);
    $prop = $ref->getProperty('documents');
    $prop->setAccessible(true);
    /** @var array<string, array<string, VectorDocument>> $docs */
    $docs = $prop->getValue($store);
    $docs['ns']['legacy'] = new VectorDocument('legacy', [1.0, 0.0, 0.0], []);
    $prop->setValue($store, $docs);

    $hits = $store->search('ns', new VectorSearchQuery(embedding: [1.0, 0.0], limit: 10, filter: []));

    expect($hits)->toHaveCount(1)
        ->and($hits[0]->id)->toBe('two');
});

it('applies the same rules on FakeVectorStore', function (): void {
    $fake = new FakeVectorStore();
    $fake->upsert('ns', [new VectorDocument('a', [0.0, 1.0])]);

    expect(fn () => $fake->upsert('ns', [new VectorDocument('b', [1.0])]))
        ->toThrow(VectorOperationException::class);

    expect($fake->referenceEmbeddingDimensions('ns'))->toBe(2);
});

it('database store rejects mixed width upsert inside a transaction', function (): void {
    Schema::dropIfExists('ai_agent_vector_documents');

    /** @var Migration $migration */
    $migration = require __DIR__.'/../database/migrations/create_ai_agent_vector_documents_table.php.stub';
    $migration->up();

    $store = new DatabaseVectorStore(DB::connection('testing'), 'ai_agent_vector_documents');

    $store->upsert('ns', [new VectorDocument('a', [1.0, 0.0])]);

    expect(fn () => $store->upsert('ns', [new VectorDocument('b', [1.0])]))
        ->toThrow(VectorOperationException::class);

    expect(DB::table('ai_agent_vector_documents')->where('namespace', 'ns')->count())->toBe(1);
});

it('container vector store rejects width mismatch', function (): void {
    config()->set('ai-agent-kit.vector.default_driver', 'in_memory');
    forgetResolvedVectorStore();

    /** @var VectorStoreInterface $store */
    $store = app(VectorStoreInterface::class);
    $store->upsert('ns', [new VectorDocument('a', [1.0, 0.0])]);

    expect(fn () => $store->upsert('ns', [new VectorDocument('b', [1.0])]))
        ->toThrow(VectorOperationException::class);
});
