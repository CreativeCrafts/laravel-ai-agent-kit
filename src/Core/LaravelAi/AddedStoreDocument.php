<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\LaravelAi;

/**
 * Document added to a provider vector store (`Store::add`).
 */
final readonly class AddedStoreDocument
{
    public function __construct(
        public string $documentId,
        public ?string $storedFileId,
    ) {
    }
}
