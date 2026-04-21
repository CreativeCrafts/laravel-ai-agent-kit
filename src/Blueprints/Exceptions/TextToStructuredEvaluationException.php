<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints\Exceptions;

use CreativeCrafts\LaravelAiAgentKit\Observability\Support\FailureCategory;
use RuntimeException;
use Throwable;

final class TextToStructuredEvaluationException extends RuntimeException
{
    private function __construct(
        string $message,
        private readonly string $failureCategory,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            previous: $previous,
        );
    }

    public static function invalidSpecialistPayload(string $reason): self
    {
        return new self(
            sprintf('TextToStructuredEvaluation specialist payload is invalid: %s', $reason),
            FailureCategory::InvalidOutput->value,
        );
    }

    public static function invalidJson(string $output, ?Throwable $previous = null): self
    {
        return new self(
            sprintf(
                'TextToStructuredEvaluation specialist output must be valid JSON. Received: %s',
                self::redactedOutputSummary($output),
            ),
            FailureCategory::MalformedOutput->value,
            $previous,
        );
    }

    public static function refusedStructuredOutput(string $output): self
    {
        return new self(
            sprintf(
                'TextToStructuredEvaluation specialist refused to return structured output. Received: %s',
                self::redactedOutputSummary($output),
            ),
            FailureCategory::Refusal->value,
        );
    }

    public function failureCategory(): string
    {
        return $this->failureCategory;
    }

    private static function redactedOutputSummary(string $output): string
    {
        return sprintf('[redacted output; length=%d chars]', strlen($output));
    }
}
