<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime\Exceptions;

use RuntimeException;

final class BlueprintCompilationException extends RuntimeException
{
    public static function missingRunId(string $promptName): self
    {
        return new self(
            sprintf(
                'Prompt blueprint [%s] must define a non-empty runId before compilation.',
                $promptName,
            ),
        );
    }
}
