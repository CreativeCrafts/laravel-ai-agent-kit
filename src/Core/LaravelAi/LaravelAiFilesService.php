<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\LaravelAi;

use CreativeCrafts\LaravelAiAgentKit\Observability\Events\LaravelAiFilesGatewayOperationFinished;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\UploadedFile;
use Laravel\Ai\Contracts\Files\StorableFile;
use Laravel\Ai\Files;
use Throwable;

final readonly class LaravelAiFilesService
{
    public function __construct(
        private ConfigRepository $config,
        private Dispatcher $events,
    ) {
    }

    public function get(string $fileId, ?string $provider = null): ProviderFileContents
    {
        $resolved = $this->resolveFileProvider($provider);

        try {
            $response = Files::get($fileId, $resolved);
            $dto = new ProviderFileContents(
                id: $response->id,
                mimeType: $response->mimeType(),
                content: $response->content(),
            );
            $this->dispatchFinished('get', $resolved, $fileId, true);

            return $dto;
        } catch (Throwable $throwable) {
            $this->dispatchFinished('get', $resolved, $fileId, false, $throwable);
            throw $throwable;
        }
    }

    public function put(
        StorableFile|UploadedFile|string $file,
        ?string $mimeType = null,
        ?string $name = null,
        ?string $provider = null,
    ): StoredProviderFile {
        $resolved = $this->resolveFileProvider($provider);

        try {
            $stored = Files::put($file, $mimeType, $name, $resolved);
            $dto = new StoredProviderFile(id: $stored->id());
            $this->dispatchFinished('put', $resolved, $stored->id(), true);

            return $dto;
        } catch (Throwable $throwable) {
            $this->dispatchFinished('put', $resolved, null, false, $throwable);
            throw $throwable;
        }
    }

    public function putFromPath(
        string $path,
        ?string $mimeType = null,
        ?string $name = null,
        ?string $provider = null,
    ): StoredProviderFile {
        $resolved = $this->resolveFileProvider($provider);

        try {
            $stored = Files::putFromPath($path, $mimeType, $name, $resolved);
            $dto = new StoredProviderFile(id: $stored->id());
            $this->dispatchFinished('put_from_path', $resolved, $stored->id(), true);

            return $dto;
        } catch (Throwable $throwable) {
            $this->dispatchFinished('put_from_path', $resolved, null, false, $throwable);
            throw $throwable;
        }
    }

    public function putFromStorage(
        string $path,
        ?string $disk = null,
        ?string $name = null,
        ?string $provider = null,
    ): StoredProviderFile {
        $resolved = $this->resolveFileProvider($provider);

        try {
            $stored = Files::putFromStorage($path, $disk, $name, $resolved);
            $dto = new StoredProviderFile(id: $stored->id());
            $this->dispatchFinished('put_from_storage', $resolved, $stored->id(), true);

            return $dto;
        } catch (Throwable $throwable) {
            $this->dispatchFinished('put_from_storage', $resolved, null, false, $throwable);
            throw $throwable;
        }
    }

    public function delete(string $fileId, ?string $provider = null): void
    {
        $resolved = $this->resolveFileProvider($provider);

        try {
            Files::delete($fileId, $resolved);
            $this->dispatchFinished('delete', $resolved, $fileId, true);
        } catch (Throwable $throwable) {
            $this->dispatchFinished('delete', $resolved, $fileId, false, $throwable);
            throw $throwable;
        }
    }

    private function resolveFileProvider(?string $provider): ?string
    {
        if ($provider !== null) {
            return $provider;
        }

        $default = $this->config->get('ai-agent-kit.laravel_ai_files.default_provider');

        return is_string($default) && $default !== '' ? $default : null;
    }

    private function dispatchFinished(
        string $operation,
        ?string $provider,
        ?string $resourceId,
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

        $this->events->dispatch(new LaravelAiFilesGatewayOperationFinished(
            operation: $operation,
            provider: $provider,
            resourceId: $resourceId,
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
