<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\LaravelAi;

/**
 * File metadata and optional body from Laravel AI `Files::get`.
 */
final readonly class ProviderFileContents
{
    public function __construct(
        public string $id,
        public ?string $mimeType,
        public ?string $content,
    ) {
    }
}
