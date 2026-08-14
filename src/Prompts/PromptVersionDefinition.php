<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Prompts;

/**
 * Defines one version from a file prompt manifest.
 *
 * @internal
 */
final readonly class PromptVersionDefinition
{
    /**
     * @param list<string>|null $variables Null preserves legacy variable inference; an empty list is authoritative.
     */
    public function __construct(
        public string $version,
        public string $templateFile,
        public ?array $variables,
        public ?string $description,
    ) {
    }
}
