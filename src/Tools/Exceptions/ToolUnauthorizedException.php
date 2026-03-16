<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tools\Exceptions;

use RuntimeException;

final class ToolUnauthorizedException extends RuntimeException
{
    public static function forName(string $name): self
    {
        return new self("Tool [{$name}] is not authorized for execution.");
    }
}
