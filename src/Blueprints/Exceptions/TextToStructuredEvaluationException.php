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
            sprintf(
                'TextToStructuredEvaluation specialist output must be valid JSON. Received: %s',
                self::redactedOutputSummary($output),
            ),
            previous: $previous,
        );
    }

    public static function refusedStructuredOutput(string $output): self
    {
        return new self(
            sprintf(
                'TextToStructuredEvaluation specialist refused to return structured output. Received: %s',
                self::redactedOutputSummary($output),
            ),
        );
    }

    private static function redactedOutputSummary(string $output): string
    {
        return sprintf('[redacted output; length=%d chars]', strlen($output));
    }
}
