<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Modality\Exceptions;

use CreativeCrafts\LaravelAiAgentKit\Core\Modality\TranscriptionAudioSourceKind;
use RuntimeException;

final class UnsupportedTranscriptionAudioSourceException extends RuntimeException
{
    public static function forSourceKind(TranscriptionAudioSourceKind $kind): self
    {
        return new self(
            sprintf(
                'Transcription audio source [%s] is not supported by the installed Laravel AI SDK transcription bridge. Use base64, path, storage, or upload input, or provide a custom Agent Kit transcription runtime.',
                $kind->value,
            ),
        );
    }
}
