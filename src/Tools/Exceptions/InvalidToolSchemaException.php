<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions;

use RuntimeException;

final class InvalidToolSchemaException extends RuntimeException
{
    public static function because(string $toolName, string $reason): self
    {
        return new self("Tool [{$toolName}] has an invalid input schema: {$reason}");
    }
}
