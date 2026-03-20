<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions;

use RuntimeException;
use Throwable;

final class RuntimeExecutionException extends RuntimeException
{
    public static function forRequest(string $runId, Throwable $previous): self
    {
        return new self(
            message: "AI runtime execution failed for run [{$runId}]",
            previous: $previous,
        );
    }
}
