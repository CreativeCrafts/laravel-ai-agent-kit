<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions;

use RuntimeException;

final class MissingPromptVariableException extends RuntimeException
{
    /**
     * @param list<string> $missingVariables
     */
    public static function forTemplate(string $name, string $version, array $missingVariables): self
    {
        $missing = implode(', ', $missingVariables);

        return new self("Prompt [{$name}] version [{$version}] is missing required variables: {$missing}.");
    }
}
