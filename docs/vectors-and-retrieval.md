# Vectors and retrieval

Agent Kit has two retrieval surfaces:

- application-owned embeddings through `VectorStoreInterface`
- provider-hosted files and stores through Laravel AI Files/Stores wrappers

Use the surface that matches your source of truth. Do not mix the two as if they were the same storage system.

## Application-owned vector storage

Use `VectorStoreInterface` when your application owns embeddings and metadata.

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Vector\VectorStoreInterface;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorDocument;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorSearchQuery;

final class SupportKnowledgeService
{
    public function __construct(
        private VectorStoreInterface $vectorStore,
    ) {
    }

    public function indexAndSearch(): array
    {
        $this->vectorStore->upsert('support', [
            new VectorDocument(
                id: 'doc-001',
                embedding: [0.8, 0.2, 0.1],
                metadata: ['topic' => 'refunds'],
            ),
        ]);

        return $this->vectorStore->search(
            'support',
            new VectorSearchQuery(
                embedding: [0.9, 0.1, 0.0],
                limit: 3,
                filter: ['topic' => 'refunds'],
            ),
        );
    }
}
~~~

## Drivers

Configure the vector driver in `config/ai-agent-kit.php`:

~~~php
'vector' => [
    'default_driver' => 'in_memory',
],
~~~

Available built-in drivers:

- `in_memory`: process-local, useful for tests and local experiments.
- `database`: SQL persistence through the package vector document table.

You may bind a custom `VectorStoreInterface` for services such as Pinecone, Qdrant, or pgvector-backed application storage.

## Upsert semantics

`upsert()` is idempotent for a namespace/document pair. The database driver writes rows with an atomic database upsert keyed by `namespace` and `document_id`, so repeated writes replace `embedding`, `metadata`, and `updated_at` without changing the original `created_at` value on existing rows.

Empty upsert batches are no-ops.

## Embedding width rule

Built-in vector stores enforce one embedding width per namespace. The first upsert into a namespace establishes the width; later documents in that namespace must match it.

Use separate namespaces when you intentionally use different embedding models or dimensions.

## Similarity search tool

The optional package `similarity_search` tool embeds a query and searches `VectorStoreInterface`. Enable it only after configuring your vector store and tool authorizer.

See [Tools](tools.md).

## Provider Files and Stores

Laravel AI provider Files/Stores are provider-hosted resources. Use the package wrappers when you need provider uploads, provider stores, or provider RAG with `FileSearch`.

- `LaravelAiFilesService` wraps provider file operations.
- `LaravelAiStoresService` wraps provider store operations.
- `FileSearch` provider tools are selected through runtime request provider-tool names.

Files/Stores gateway operations emit redacted events; no file bodies or API keys are included.

## SDK vector stores versus Agent Kit vectors

Laravel AI SDK/provider vector or retrieval facilities are direct-SDK/provider-hosted surfaces. Use them directly when your application wants provider-native retrieval behavior, provider-hosted indexes, or SDK-specific store semantics.

Use Agent Kit `VectorStoreInterface` when your application owns the vectors and wants package-owned persistence, deterministic fakes, `SimilaritySearchTool`, and package testing patterns. Agent Kit does not treat provider-hosted stores and application-owned vectors as interchangeable.

## Choosing a retrieval surface

| Need | Use |
|------|-----|
| App owns embeddings and metadata | `VectorStoreInterface` |
| App wants simple local/test vector search | `in_memory` vector driver |
| App wants SQL-backed package vectors | `database` vector driver |
| Provider hosts files and RAG stores | Laravel AI Files/Stores wrappers + `FileSearch` |
| Provider-native vector/store semantics | Laravel AI SDK directly |
| Tool-driven search over package vectors | `similarity_search` tool |

## Production notes

- `in_memory` is not shared across workers.
- Database vector search may scan rows in the namespace; configure scan limits where needed.
- Database vector upserts are atomic per `namespace` and `document_id`, but namespace dimension validation still runs before writes.
- Keep vector metadata safe for logs and telemetry.
- Match embedding dimensions deliberately.

See [Production](production.md).
