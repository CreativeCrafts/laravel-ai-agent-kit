<?php

namespace CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions;

use RuntimeException;

class ProviderDisabledException extends RuntimeException
{
    public static function named(string $providerName): self
    {
        return new self("Provider [{$providerName}] is disabled.");
    }
}
