<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions;

use CreativeCrafts\LaravelAiAgentKit\Observability\Contracts\HasFailureCategory;
use CreativeCrafts\LaravelAiAgentKit\Observability\Support\FailureCategory;
use RuntimeException;

final class ToolAuthorizationDeniedException extends RuntimeException implements HasFailureCategory
{
    public static function forProviderTool(string $name): self
    {
        return new self(
            message: sprintf('Provider tool [%s] is not authorized for use.', $name),
        );
    }

    public function failureCategory(): string
    {
        return FailureCategory::ExecutionFailed->value;
    }
}
