<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions;

use RuntimeException;

class ProviderNotDefinedException extends RuntimeException
{
    public static function named(string $providerName): self
    {
        return new self("Provider [{$providerName}] is not defined.");
    }
}
