<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Prompts;

use CreativeCrafts\LaravelAiAgentKit\Prompts\PromptTemplate;

interface PromptRepository
{
    public function has(string $name, ?string $version = null): bool;

    public function get(string $name, ?string $version = null): PromptTemplate;

    /**
     * @param array<string, scalar|null> $variables
     */
    public function render(string $name, array $variables = [], ?string $version = null): string;
}
