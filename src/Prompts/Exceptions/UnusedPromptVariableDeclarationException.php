<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions;

/** Raised when an explicit prompt variable declaration has no dynamic placeholder. */
final class UnusedPromptVariableDeclarationException extends InvalidPromptManifestException
{
    /**
     * Create an exception for unused explicit variable declarations.
     *
     * @param list<string> $variables
     */
    public static function forTemplate(string $name, string $version, array $variables): self
    {
        $unused = implode(', ', $variables);

        return new self(
            "Prompt [{$name}] version [{$version}] declares unused variables: {$unused}.",
        );
    }
}
