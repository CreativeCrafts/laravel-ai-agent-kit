<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\LaravelAi;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\UploadedFile;
use Laravel\Ai\Contracts\Files\StorableFile;
use Laravel\Ai\Files;

final readonly class LaravelAiFilesService
{
    public function __construct(
        private ConfigRepository $config,
    ) {
    }

    public function get(string $fileId, ?string $provider = null): ProviderFileContents
    {
        $response = Files::get($fileId, $this->resolveFileProvider($provider));

        return new ProviderFileContents(
            id: $response->id,
            mimeType: $response->mimeType(),
            content: $response->content(),
        );
    }

    public function put(
        StorableFile|UploadedFile|string $file,
        ?string $mimeType = null,
        ?string $name = null,
        ?string $provider = null,
    ): StoredProviderFile {
        $stored = Files::put($file, $mimeType, $name, $this->resolveFileProvider($provider));

        return new StoredProviderFile(id: $stored->id());
    }

    public function putFromPath(
        string $path,
        ?string $mimeType = null,
        ?string $name = null,
        ?string $provider = null,
    ): StoredProviderFile {
        $stored = Files::putFromPath($path, $mimeType, $name, $this->resolveFileProvider($provider));

        return new StoredProviderFile(id: $stored->id());
    }

    public function putFromStorage(
        string $path,
        ?string $disk = null,
        ?string $name = null,
        ?string $provider = null,
    ): StoredProviderFile {
        $stored = Files::putFromStorage($path, $disk, $name, $this->resolveFileProvider($provider));

        return new StoredProviderFile(id: $stored->id());
    }

    public function delete(string $fileId, ?string $provider = null): void
    {
        Files::delete($fileId, $this->resolveFileProvider($provider));
    }

    private function resolveFileProvider(?string $provider): ?string
    {
        if ($provider !== null) {
            return $provider;
        }

        $default = $this->config->get('ai-agent-kit.laravel_ai_files.default_provider');

        return is_string($default) && $default !== '' ? $default : null;
    }
}
