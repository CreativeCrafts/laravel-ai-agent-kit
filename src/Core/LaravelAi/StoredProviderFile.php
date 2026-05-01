<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\LaravelAi;

/**
 * Provider-hosted file id after upload (Laravel AI `Files::put`).
 */
final readonly class StoredProviderFile
{
    public function __construct(
        public string $id,
    ) {
    }
}
