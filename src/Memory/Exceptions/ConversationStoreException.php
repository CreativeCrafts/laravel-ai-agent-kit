<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Memory\Exceptions;

use RuntimeException;
use Throwable;

class ConversationStoreException extends RuntimeException
{
    public static function payloadEncodingFailed(string $field, Throwable $previous): self
    {
        return new self(
            message: "Failed to encode conversation payload for [{$field}].",
            previous: $previous,
        );
    }

    public static function payloadDecodingFailed(string $field, Throwable $previous): self
    {
        return new self(
            message: "Failed to decode conversation payload for [{$field}].",
            previous: $previous,
        );
    }

    public static function payloadEncryptionFailed(string $field, Throwable $previous): self
    {
        return new self(
            message: "Failed to encrypt conversation payload for [{$field}].",
            previous: $previous,
        );
    }

    public static function payloadDecryptionFailed(string $field, Throwable $previous): self
    {
        return new self(
            message: "Failed to decrypt conversation payload for [{$field}].",
            previous: $previous,
        );
    }
}
