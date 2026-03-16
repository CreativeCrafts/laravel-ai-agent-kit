<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tools;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\Tool;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\ToolAuthorizer;

final class DenyAllToolAuthorizer implements ToolAuthorizer
{
    /**
     * @param array<string, mixed> $input
     */
    public function authorize(Tool $tool, array $input): bool
    {
        return false;
    }
}
