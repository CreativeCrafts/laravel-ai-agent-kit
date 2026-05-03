<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\LaravelAi;

use CreativeCrafts\LaravelAiAgentKit\Observability\Events\LaravelAiStoresGatewayOperationFinished;
use DateInterval;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Files\StorableFile;
use Laravel\Ai\Responses\AddedDocumentResponse;
use Laravel\Ai\Store;
use Laravel\Ai\Stores;
use Throwable;

final readonly class LaravelAiStoresService
{
    public function __construct(
        private ConfigRepository $config,
        private Dispatcher $events,
    ) {
    }

    public function get(string $storeId, ?string $provider = null): ProviderVectorStoreState
    {
        $resolved = $this->resolveStoreProvider($provider);

        try {
            $state = $this->mapStore(Stores::get($storeId, $resolved));
            $this->dispatchFinished('get', $resolved, $storeId, null, true);

            return $state;
        } catch (Throwable $throwable) {
            $this->dispatchFinished('get', $resolved, $storeId, null, false, $throwable);
            throw $throwable;
        }
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
        $resolved = $this->resolveStoreProvider($provider);

        try {
            $collection = Collection::make($fileIds);
            $state = $this->mapStore(Stores::create(
                $name,
                $description,
                $collection,
                $expiresWhenIdleFor,
                $resolved,
            ));
            $this->dispatchFinished('create', $resolved, $state->id, null, true);

            return $state;
        } catch (Throwable $throwable) {
            $this->dispatchFinished('create', $resolved, null, null, false, $throwable);
            throw $throwable;
        }
    }

    public function deleteStore(string $storeId, ?string $provider = null): bool
    {
        $resolved = $this->resolveStoreProvider($provider);

        try {
            $ok = Stores::delete($storeId, $resolved);
            $this->dispatchFinished('delete_store', $resolved, $storeId, null, $ok);

            return $ok;
        } catch (Throwable $throwable) {
            $this->dispatchFinished('delete_store', $resolved, $storeId, null, false, $throwable);
            throw $throwable;
        }
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function addToStore(
        string $storeId,
        string|StorableFile|UploadedFile $file,
        array $metadata = [],
        ?string $provider = null,
    ): AddedStoreDocument {
        $resolved = $this->resolveStoreProvider($provider);

        try {
            $store = Stores::get($storeId, $resolved);
            $response = $store->add($file, $metadata);
            $dto = $this->mapAdded($response);
            $this->dispatchFinished('add_to_store', $resolved, $storeId, $dto->documentId, true);

            return $dto;
        } catch (Throwable $throwable) {
            $this->dispatchFinished('add_to_store', $resolved, $storeId, null, false, $throwable);
            throw $throwable;
        }
    }

    public function removeFromStore(
        string $storeId,
        string $documentId,
        bool $deleteFile = false,
        ?string $provider = null,
    ): bool {
        $resolved = $this->resolveStoreProvider($provider);

        try {
            $store = Stores::get($storeId, $resolved);
            $ok = $store->remove($documentId, $deleteFile);
            $this->dispatchFinished('remove_from_store', $resolved, $storeId, $documentId, $ok);

            return $ok;
        } catch (Throwable $throwable) {
            $this->dispatchFinished('remove_from_store', $resolved, $storeId, $documentId, false, $throwable);
            throw $throwable;
        }
    }

    public function refreshStore(string $storeId, ?string $provider = null): ProviderVectorStoreState
    {
        $resolved = $this->resolveStoreProvider($provider);

        try {
            $store = Stores::get($storeId, $resolved);
            $state = $this->mapStore($store->refresh());
            $this->dispatchFinished('refresh_store', $resolved, $storeId, null, true);

            return $state;
        } catch (Throwable $throwable) {
            $this->dispatchFinished('refresh_store', $resolved, $storeId, null, false, $throwable);
            throw $throwable;
        }
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

    private function dispatchFinished(
        string $operation,
        ?string $provider,
        ?string $storeId,
        ?string $documentId,
        bool $success,
        ?Throwable $failure = null,
    ): void {
        if (!$this->filesStoresObservabilityEnabled()) {
            return;
        }

        $errorClass = null;
        $errorSummary = null;
        if ($failure !== null) {
            $parts = GatewayOperationErrorSummary::fromThrowable($failure);
            $errorClass = $parts['class'];
            $errorSummary = $parts['summary'];
        }

        $this->events->dispatch(new LaravelAiStoresGatewayOperationFinished(
            operation: $operation,
            provider: $provider,
            storeId: $storeId,
            documentId: $documentId,
            success: $success,
            errorClass: $errorClass,
            errorSummary: $errorSummary,
        ));
    }

    private function filesStoresObservabilityEnabled(): bool
    {
        $block = $this->config->get('ai-agent-kit.observability.laravel_ai_files_stores', []);

        return !is_array($block) || ($block['enabled'] ?? true) !== false;
    }
}
