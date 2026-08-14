<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions;

/** Raised when a template uses variables absent from its explicit manifest declaration. */
final class UndeclaredPromptVariableException extends InvalidPromptManifestException
{
    /**
     * Create an exception for undeclared dynamic placeholders.
     *
     * @param list<string> $variables
     */
    public static function forTemplate(string $name, string $version, array $variables): self
    {
        $undeclared = implode(', ', $variables);

        return new self(
            "Prompt [{$name}] version [{$version}] uses undeclared variables: {$undeclared}.",
        );
    }
}
