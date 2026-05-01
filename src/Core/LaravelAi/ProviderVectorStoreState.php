<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\LaravelAi;

/**
 * Snapshot of a Laravel AI provider vector store (`Stores::get` / `Stores::create`).
 */
final readonly class ProviderVectorStoreState
{
    public function __construct(
        public string $id,
        public ?string $name,
        public StoreFileCountsDto $fileCounts,
        public bool $ready,
    ) {
    }
}
