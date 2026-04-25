<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions;

use CreativeCrafts\LaravelAiAgentKit\Observability\Contracts\HasFailureCategory;
use CreativeCrafts\LaravelAiAgentKit\Observability\Support\FailureCategory;
use RuntimeException;

final class SchemaResolutionException extends RuntimeException implements HasFailureCategory
{
    public static function forMissingClass(string $class): self
    {
        return new self(
            message: sprintf(
                'Structured-output schema class [%s] does not exist and cannot be resolved.',
                $class,
            ),
        );
    }

    public static function forContractMismatch(string $class): self
    {
        return new self(
            message: sprintf(
                'Structured-output schema class [%s] must implement [Laravel\\Ai\\Contracts\\HasStructuredOutput].',
                $class,
            ),
        );
    }

    public static function forUnsupportedSchemaShape(string $actualType): self
    {
        return new self(
            message: sprintf(
                'Structured-output schema must be a Closure, Laravel\\Ai\\ObjectSchema, or class-string<Laravel\\Ai\\Contracts\\HasStructuredOutput>; received [%s].',
                $actualType,
            ),
        );
    }

    public function failureCategory(): string
    {
        return FailureCategory::ExecutionFailed->value;
    }
}
