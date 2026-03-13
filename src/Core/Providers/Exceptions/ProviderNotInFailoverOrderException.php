<?php

namespace CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions;

use RuntimeException;

class ProviderNotInFailoverOrderException extends RuntimeException
{
    public static function named(string $providerName): self
    {
        return new self("Provider [{$providerName}] is not present in failover_order.");
    }
}
