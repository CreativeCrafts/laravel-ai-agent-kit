<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions;

use RuntimeException;
use Throwable;

/** Raised when prompt metadata cannot form a valid manifest. */
class InvalidPromptManifestException extends RuntimeException
{
    /** Create an exception for a malformed manifest field. */
    public static function forField(string $metadataPath, string $field, string $requirement): self
    {
        return new self("Prompt manifest [{$metadataPath}] field [{$field}] {$requirement}.");
    }

    /** Create an exception for a current version absent from the version map. */
    public static function forUnregisteredCurrentVersion(
        string $metadataPath,
        string $currentVersion,
    ): self {
        return new self(
            "Prompt manifest [{$metadataPath}] current_version [{$currentVersion}] is not registered in [versions].",
        );
    }

    /** Create an exception for a referenced template file that cannot be read. */
    public static function forMissingTemplate(
        string $name,
        string $version,
        string $templatePath,
    ): self {
        return new self(
            "Prompt [{$name}] version [{$version}] references an unreadable template file [{$templatePath}].",
        );
    }

    /** Normalize a metadata loading failure while retaining the original cause. */
    public static function forLoadFailure(string $metadataPath, Throwable $previous): self
    {
        return new self(
            "Prompt manifest [{$metadataPath}] could not be loaded: {$previous->getMessage()}",
            previous: $previous,
        );
    }
}
