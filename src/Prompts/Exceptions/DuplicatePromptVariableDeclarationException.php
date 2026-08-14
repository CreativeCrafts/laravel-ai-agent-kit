<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions;

/** Raised when a manifest declares the same prompt variable more than once. */
final class DuplicatePromptVariableDeclarationException extends InvalidPromptManifestException
{
    /** Create an exception for a duplicate variable declaration. */
    public static function forDeclaration(string $name, string $version, string $variable): self
    {
        return new self(
            "Prompt [{$name}] version [{$version}] declares variable [{$variable}] more than once.",
        );
    }
}
