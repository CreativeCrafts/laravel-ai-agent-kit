<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\LaravelAi;

use DateInterval;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Files\StorableFile;
use Laravel\Ai\Responses\AddedDocumentResponse;
use Laravel\Ai\Store;
use Laravel\Ai\Stores;

final readonly class LaravelAiStoresService
{
    public function __construct(
        private ConfigRepository $config,
    ) {
    }

    public function get(string $storeId, ?string $provider = null): ProviderVectorStoreState
    {
        return $this->mapStore(Stores::get($storeId, $this->resolveStoreProvider($provider)));
    }

    /**
     * @param list<string> $fileIds
     */
    public function create(
        string $name,
        ?string $description = null,
        array $fileIds = [],
        ?DateInterval $expiresWhenIdleFor = null,
        ?string $provider = null,
    ): ProviderVectorStoreState {
        $collection = Collection::make($fileIds);

        return $this->mapStore(Stores::create(
            $name,
            $description,
            $collection,
            $expiresWhenIdleFor,
            $this->resolveStoreProvider($provider),
        ));
    }

    public function deleteStore(string $storeId, ?string $provider = null): bool
    {
        return Stores::delete($storeId, $this->resolveStoreProvider($provider));
    }

    /**
     * Add a provider file reference (or raw content string per SDK rules) to a store.
     *
     * @param array<string, mixed> $metadata
     */
    public function addToStore(
        string $storeId,
        string|StorableFile|UploadedFile $file,
        array $metadata = [],
        ?string $provider = null,
    ): AddedStoreDocument {
        $store = Stores::get($storeId, $this->resolveStoreProvider($provider));
        $response = $store->add($file, $metadata);

        return $this->mapAdded($response);
    }

    public function removeFromStore(
        string $storeId,
        string $documentId,
        bool $deleteFile = false,
        ?string $provider = null,
    ): bool {
        $store = Stores::get($storeId, $this->resolveStoreProvider($provider));

        return $store->remove($documentId, $deleteFile);
    }

    public function refreshStore(string $storeId, ?string $provider = null): ProviderVectorStoreState
    {
        $store = Stores::get($storeId, $this->resolveStoreProvider($provider));

        return $this->mapStore($store->refresh());
    }

    private function resolveStoreProvider(?string $provider): ?string
    {
        if ($provider !== null) {
            return $provider;
        }

        $default = $this->config->get('ai-agent-kit.laravel_ai_stores.default_provider');

        return is_string($default) && $default !== '' ? $default : null;
    }

    private function mapStore(Store $store): ProviderVectorStoreState
    {
        $counts = $store->fileCounts;

        return new ProviderVectorStoreState(
            id: $store->id,
            name: $store->name,
            fileCounts: new StoreFileCountsDto(
                completed: $counts->completed,
                pending: $counts->pending,
                failed: $counts->failed,
            ),
            ready: $store->ready,
        );
    }

    private function mapAdded(AddedDocumentResponse $response): AddedStoreDocument
    {
        return new AddedStoreDocument(
            documentId: $response->id(),
            storedFileId: $response->fileId(),
        );
    }
}
