<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Config\Exceptions;

use RuntimeException;

class InvalidConfigurationException extends RuntimeException
{
    public static function missingKey(string $key): self
    {
        return new self("Missing required config key: {$key}");
    }

    public static function invalidType(string $key, string $expected): self
    {
        return new self("Invalid type for config key: {$key}. Expected {$expected}.");
    }

    public static function invalidValue(string $key, string $message): self
    {
        return new self("Invalid value for config key: {$key}. {$message}");
    }
}
