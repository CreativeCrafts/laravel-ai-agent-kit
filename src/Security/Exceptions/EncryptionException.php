<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Security\Exceptions;

use RuntimeException;
use Throwable;

final class EncryptionException extends RuntimeException
{
    public static function encryptionFailed(?Throwable $previous = null): self
    {
        return new self('Failed to encrypt the provided plaintext value.', previous: $previous);
    }

    public static function decryptionFailed(?Throwable $previous = null): self
    {
        return new self('Failed to decrypt the provided ciphertext value.', previous: $previous);
    }
}
