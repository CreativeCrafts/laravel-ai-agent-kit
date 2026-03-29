<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints\Exceptions;

use RuntimeException;
use Throwable;

final class TextToStructuredEvaluationException extends RuntimeException
{
    public static function invalidSpecialistPayload(string $reason): self
    {
        return new self(sprintf('TextToStructuredEvaluation specialist payload is invalid: %s', $reason));
    }

    public static function invalidJson(string $output, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('TextToStructuredEvaluation specialist output must be valid JSON. Received: %s', $output),
            previous: $previous,
        );
    }
}
