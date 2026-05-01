<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Modality\EmbeddingsRuntime;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\Tool;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolAuthorizer;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions\ToolUnauthorizedException;
use CreativeCrafts\LaravelAiAgentKit\Tools\InMemoryToolRegistry;
use CreativeCrafts\LaravelAiAgentKit\Tools\SimilaritySearchTool;
use CreativeCrafts\LaravelAiAgentKit\Vector\InMemoryVectorStore;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorDocument;
use Illuminate\Support\Facades\Config;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\Embeddings;

beforeEach(function (): void {
    app()->register(AiServiceProvider::class);

    /** @var array<string, mixed> $ai */
    $ai = require __DIR__.'/../vendor/laravel/ai/config/ai.php';
    Config::set('ai', $ai);
    Config::set('ai.default', 'openai');
    Config::set('ai.default_for_embeddings', 'openai');
    Config::set('ai.providers', [
        'openai' => [
            'driver' => 'openai',
            'key' => 'test-key-for-ci',
        ],
    ]);
});

it('returns ranked vector hits for a query', function (): void {
    Embeddings::fake([[[1.0, 0.0, 0.0]]])->preventStrayEmbeddings();

    app()->forgetInstance(EmbeddingsRuntime::class);

    Config::set('ai-agent-kit.tools.similarity_search', [
        'default_namespace' => 'kb',
        'default_limit' => 5,
        'embedding_dimensions' => 3,
        'embedding_provider' => 'openai',
        'embedding_model' => 'text-embedding-3-small',
    ]);

    $store = new InMemoryVectorStore();
    $store->upsert('kb', [
        new VectorDocument('doc-1', [1.0, 0.0, 0.0], ['label' => 'alpha']),
        new VectorDocument('doc-2', [0.0, 1.0, 0.0], ['label' => 'beta']),
    ]);

    $tool = new SimilaritySearchTool(
        embeddingsRuntime: app(EmbeddingsRuntime::class),
        vectorStore: $store,
        config: config(),
    );

    $out = $tool->execute(['query' => 'find alpha']);

    expect($out['empty'])->toBeFalse()
        ->and($out['results'])->toHaveCount(2)
        ->and($out['results'][0]['id'])->toBe('doc-1')
        ->and($out['results'][0]['score'])->toBeGreaterThan($out['results'][1]['score']);
});

it('returns empty when no documents match the namespace', function (): void {
    Embeddings::fake([[[1.0, 0.0, 0.0]]])->preventStrayEmbeddings();

    app()->forgetInstance(EmbeddingsRuntime::class);

    Config::set('ai-agent-kit.tools.similarity_search', [
        'default_namespace' => 'other',
        'embedding_dimensions' => 3,
    ]);

    $store = new InMemoryVectorStore();
    $store->upsert('kb', [new VectorDocument('doc-1', [1.0, 0.0, 0.0])]);

    $tool = new SimilaritySearchTool(
        embeddingsRuntime: app(EmbeddingsRuntime::class),
        vectorStore: $store,
        config: config(),
    );

    $out = $tool->execute(['query' => 'hello']);

    expect($out['empty'])->toBeTrue()
        ->and($out['results'])->toBe([]);
});

it('applies filter_json metadata constraints', function (): void {
    Embeddings::fake([[[1.0, 0.0, 0.0]]])->preventStrayEmbeddings();

    app()->forgetInstance(EmbeddingsRuntime::class);

    Config::set('ai-agent-kit.tools.similarity_search', [
        'default_namespace' => 'kb',
        'embedding_dimensions' => 3,
    ]);

    $store = new InMemoryVectorStore();
    $store->upsert('kb', [
        new VectorDocument('a', [1.0, 0.0, 0.0], ['scope' => 'public']),
        new VectorDocument('b', [1.0, 0.0, 0.0], ['scope' => 'private']),
    ]);

    $tool = new SimilaritySearchTool(
        embeddingsRuntime: app(EmbeddingsRuntime::class),
        vectorStore: $store,
        config: config(),
    );

    $out = $tool->execute([
        'query' => 'x',
        'filter_json' => json_encode(['scope' => 'private'], JSON_THROW_ON_ERROR),
    ]);

    expect($out['results'])->toHaveCount(1)
        ->and($out['results'][0]['id'])->toBe('b');
});

it('denies execution when the tool authorizer rejects the custom tool', function (): void {
    Embeddings::fake([[[1.0, 0.0, 0.0]]])->preventStrayEmbeddings();

    app()->forgetInstance(EmbeddingsRuntime::class);

    Config::set('ai-agent-kit.tools.similarity_search', [
        'name' => 'similarity_search',
        'default_namespace' => 'kb',
        'embedding_dimensions' => 3,
    ]);

    $store = new InMemoryVectorStore();
    $toolImpl = new SimilaritySearchTool(
        embeddingsRuntime: app(EmbeddingsRuntime::class),
        vectorStore: $store,
        config: config(),
    );

    $registry = new InMemoryToolRegistry(
        authorizer: new class () implements ToolAuthorizer {
            public function authorizeCustomTool(Tool $tool, array $input): bool
            {
                return false;
            }

            public function authorizeProviderTool(string $providerToolName): bool
            {
                return false;
            }
        },
        tools: [$toolImpl],
    );

    $registry->execute('similarity_search', ['query' => 'no']);
})->throws(ToolUnauthorizedException::class, 'similarity_search');

it('allows execution when the authorizer permits the tool', function (): void {
    Embeddings::fake([[[1.0, 0.0, 0.0]]])->preventStrayEmbeddings();

    app()->forgetInstance(EmbeddingsRuntime::class);

    Config::set('ai-agent-kit.tools.similarity_search', [
        'name' => 'similarity_search',
        'default_namespace' => 'kb',
        'embedding_dimensions' => 3,
    ]);

    $store = new InMemoryVectorStore();
    $store->upsert('kb', [new VectorDocument('z', [1.0, 0.0, 0.0])]);

    $toolImpl = new SimilaritySearchTool(
        embeddingsRuntime: app(EmbeddingsRuntime::class),
        vectorStore: $store,
        config: config(),
    );

    $registry = new InMemoryToolRegistry(
        authorizer: new class () implements ToolAuthorizer {
            public function authorizeCustomTool(Tool $tool, array $input): bool
            {
                return true;
            }

            public function authorizeProviderTool(string $providerToolName): bool
            {
                return false;
            }
        },
        tools: [$toolImpl],
    );

    $out = $registry->execute('similarity_search', ['query' => 'ok']);

    expect($out['empty'])->toBeFalse();
});

it('registers similarity_search on the container when enabled in config', function (): void {
    Config::set('ai-agent-kit.tools.similarity_search', [
        'enabled' => true,
        'register' => true,
        'default_namespace' => 'kb',
        'embedding_dimensions' => 3,
    ]);

    Embeddings::fake([[[1.0, 0.0, 0.0]]])->preventStrayEmbeddings();
    forgetResolvedVectorStore();
    app()->forgetInstance(InMemoryToolRegistry::class);
    app()->forgetInstance(ToolRegistry::class);
    app()->forgetInstance(EmbeddingsRuntime::class);

    expect(app(ToolRegistry::class)->has('similarity_search'))->toBeTrue();
});
