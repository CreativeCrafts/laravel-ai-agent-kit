<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Vector;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Vector\VectorStoreInterface;

final readonly class SdkBackedVectorAdapterStrategy
{
    public const string STRATEGY_INTERNAL_SDK_RETRIEVAL_BRIDGE = 'internal_sdk_retrieval_bridge';
    public const string STRATEGY_EXTERNAL_VECTOR_STORE_ADAPTER = 'external_vector_store_adapter';

    /**
     * @return class-string<VectorStoreInterface>
     */
    public function authoritativePort(): string
    {
        return VectorStoreInterface::class;
    }

    /**
     * @return list<class-string>
     */
    public function packageOwnedDtos(): array
    {
        return [
          VectorDocument::class,
          VectorSearchQuery::class,
          VectorSearchResult::class,
        ];
    }

    /**
     * @return list<string>
     */
    public function packageOwnedCapabilities(): array
    {
        return [
          'namespace_scoping',
          'document_upsert',
          'vector_search',
          'document_delete',
          'typed_validation',
          'typed_exceptions',
        ];
    }

    /**
     * @return list<string>
     */
    public function delegatableSdkCapabilities(): array
    {
        return [
          'embedding_generation',
          'provider_native_retrieval_execution',
          'internal_retrieval_orchestration',
        ];
    }

    public function supportsAdapterStrategy(string $strategy): bool
    {
        return in_array($strategy, $this->supportedAdapterStrategies(), true);
    }

    /**
     * @return list<string>
     */
    public function supportedAdapterStrategies(): array
    {
        return [
          self::STRATEGY_INTERNAL_SDK_RETRIEVAL_BRIDGE,
          self::STRATEGY_EXTERNAL_VECTOR_STORE_ADAPTER,
        ];
    }

    /**
     * @return list<string>
     */
    public function boundaryRules(): array
    {
        return [
          'VectorStoreInterface remains the authoritative public contract for package retrieval.',
          'SDK-backed retrieval may only be introduced behind package-owned adapters or internal orchestration helpers.',
          'Laravel AI SDK types must not leak through vector contracts, DTOs, or typed vector exceptions.',
          'SDK-backed adapters must return package-owned VectorSearchResult collections and accept package-owned VectorDocument and VectorSearchQuery inputs.',
          'Provider-specific retrieval configuration remains an internal adapter detail and must not replace package namespace, document, query, or result abstractions.',
        ];
    }
}
