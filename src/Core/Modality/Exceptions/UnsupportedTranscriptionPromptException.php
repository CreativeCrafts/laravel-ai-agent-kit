<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality\Exceptions;

use RuntimeException;

final class UnsupportedTranscriptionPromptException extends RuntimeException
{
    public static function forInstalledSdk(): self
    {
        return new self(
            'Prompted transcription was requested, but the installed Laravel AI SDK transcription pending object does not support providerOptions(...). Upgrade laravel/ai or use a transcription runtime that can honor transcription prompts.',
        );
    }
}
