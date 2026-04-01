<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints\Exceptions;

use RuntimeException;

final class AudioToTextToEvaluationException extends RuntimeException
{
    public static function invalidPayload(string $reason): self
    {
        return new self(sprintf('AudioToTextToEvaluation payload is invalid: %s', $reason));
    }
}
