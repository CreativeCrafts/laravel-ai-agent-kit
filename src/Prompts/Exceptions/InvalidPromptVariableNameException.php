<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions;

/** Raised when a manifest declares an unsupported prompt variable name. */
final class InvalidPromptVariableNameException extends InvalidPromptManifestException
{
    /** Create an exception for an invalid variable declaration. */
    public static function forDeclaration(string $name, string $version, int $index): self
    {
        return new self(
            "Prompt [{$name}] version [{$version}] variable declaration at index [{$index}] must be a non-empty string matching [A-Za-z_][A-Za-z0-9_]*.",
        );
    }
}
