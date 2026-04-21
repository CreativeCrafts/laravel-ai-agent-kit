<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions;

use CreativeCrafts\LaravelAiAgentKit\Observability\Contracts\HasFailureCategory;
use CreativeCrafts\LaravelAiAgentKit\Observability\Support\FailureCategory;
use RuntimeException;
use Throwable;

final class RuntimeExecutionException extends RuntimeException implements HasFailureCategory
{
    private function __construct(
        string $message,
        private readonly string $failureCategory,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            previous: $previous,
        );
    }

    public static function forRequest(
        string $runId,
        Throwable $previous,
        string $failureCategory = FailureCategory::ExecutionFailed->value,
    ): self {
        return new self(
            message: "AI runtime execution failed for run [{$runId}]",
            failureCategory: $failureCategory,
            previous: $previous,
        );
    }

    public function failureCategory(): string
    {
        return $this->failureCategory;
    }
}
