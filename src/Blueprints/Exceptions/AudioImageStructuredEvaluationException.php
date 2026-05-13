<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints\Exceptions;

use RuntimeException;

final class AudioImageStructuredEvaluationException extends RuntimeException
{
    public static function emptyTranscript(): self
    {
        return new self('Audio-image structured evaluation received an empty transcript. Set allowEmptyTranscript to true when the evaluation schema should classify empty or malformed audio.');
    }

    /**
     * @param list<string> $missingCapabilities
     */
    public static function missingProviderCapabilities(string $provider, array $missingCapabilities): self
    {
        return new self(
            sprintf(
                'Provider [%s] does not advertise required capabilities [%s] for audio-image structured evaluation.',
                $provider,
                implode(', ', $missingCapabilities),
            ),
        );
    }

    public static function missingImageCapability(string $provider): self
    {
        return new self(
            sprintf(
                'Provider [%s] does not advertise an image input capability [image_input or vision] for audio-image structured evaluation.',
                $provider,
            ),
        );
    }
}
