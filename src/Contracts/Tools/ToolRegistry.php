<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Tools;

interface ToolRegistry
{
    public function register(Tool $tool): void;

    public function has(string $name): bool;

    public function get(string $name): Tool;

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function execute(string $name, array $input): array;
}
