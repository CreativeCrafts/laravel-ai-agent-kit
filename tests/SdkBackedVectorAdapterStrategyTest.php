<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Vector\VectorStoreInterface;
use CreativeCrafts\LaravelAiAgentKit\Vector\SdkBackedVectorAdapterStrategy;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorDocument;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorSearchQuery;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorSearchResult;

it('documents the authoritative package-owned vector port and dto boundary', function () {
    $strategy = new SdkBackedVectorAdapterStrategy();

    expect($strategy->authoritativePort())
      ->toBe(VectorStoreInterface::class)
      ->and($strategy->packageOwnedDtos())
      ->toBe([
        VectorDocument::class,
        VectorSearchQuery::class,
        VectorSearchResult::class,
      ])
      ->and($strategy->packageOwnedCapabilities())
      ->toBe([
        'namespace_scoping',
        'document_upsert',
        'vector_search',
        'document_delete',
        'typed_validation',
        'typed_exceptions',
      ])
      ->and($strategy->delegatableSdkCapabilities())
      ->toBe([
        'embedding_generation',
        'provider_native_retrieval_execution',
        'internal_retrieval_orchestration',
      ]);
});

it('declares supported sdk-backed adapter strategies without replacing package contracts', function () {
    $strategy = new SdkBackedVectorAdapterStrategy();

    expect($strategy->supportedAdapterStrategies())
      ->toBe([
        SdkBackedVectorAdapterStrategy::STRATEGY_INTERNAL_SDK_RETRIEVAL_BRIDGE,
        SdkBackedVectorAdapterStrategy::STRATEGY_EXTERNAL_VECTOR_STORE_ADAPTER,
      ])
      ->and($strategy->supportsAdapterStrategy(SdkBackedVectorAdapterStrategy::STRATEGY_INTERNAL_SDK_RETRIEVAL_BRIDGE))
      ->toBeTrue()
      ->and($strategy->supportsAdapterStrategy('unsupported'))
      ->toBeFalse();
});

it('documents vector boundary rules that forbid sdk type leakage through public vector apis', function () {
    $strategy = new SdkBackedVectorAdapterStrategy();

    expect($strategy->boundaryRules())
      ->toHaveCount(5)
      ->and($strategy->boundaryRules()[0])->toContain('VectorStoreInterface remains the authoritative public contract')
      ->and($strategy->boundaryRules()[1])->toContain('package-owned adapters or internal orchestration helpers')
      ->and($strategy->boundaryRules()[2])->toContain('must not leak through vector contracts, DTOs, or typed vector exceptions')
      ->and($strategy->boundaryRules()[3])->toContain('must return package-owned VectorSearchResult collections')
      ->and($strategy->boundaryRules()[4])->toContain('must not replace package namespace, document, query, or result abstractions');
});
