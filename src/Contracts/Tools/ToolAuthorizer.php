<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Tools;

interface ToolAuthorizer
{
    /**
     * @param array<string, mixed> $input
     */
    public function authorize(Tool $tool, array $input): bool;
}
