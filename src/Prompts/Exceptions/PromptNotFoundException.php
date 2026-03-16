<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions;

use RuntimeException;

final class PromptNotFoundException extends RuntimeException
{
    public static function forName(string $name, ?string $version = null): self
    {
        if ($version === null) {
            return new self("Prompt [{$name}] was not found.");
        }

        return new self("Prompt [{$name}] with version [{$version}] was not found.");
    }
}
