<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints\Exceptions;

use CreativeCrafts\LaravelAiAgentKit\Blueprints\EvaluationImageInputKind;
use RuntimeException;

final class UnsupportedEvaluationImageInputException extends RuntimeException
{
    public static function forInputKind(EvaluationImageInputKind $kind): self
    {
        return new self(
            sprintf(
                'Evaluation image input [%s] is not supported by the installed Laravel AI SDK runtime attachment bridge. Use URL, base64, path, storage, or upload input, or provide a custom Agent Kit workflow.',
                $kind->value,
            ),
        );
    }
}
