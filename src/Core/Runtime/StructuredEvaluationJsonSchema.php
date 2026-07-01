<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use Laravel\Ai\ObjectSchema;

/**
 * Schema handle for TextToStructuredEvaluation specialist {@see ExecutionRequest::$schema}.
 *
 * {@see ObjectSchema} expects a map of {@see Type} instances; we use an empty object with a stable name.
 * Payload shape is still enforced by {@see StructuredEvaluationOutputNormalizer}
 * on {@see ExecutionResult::$structuredOutput} or text fallback.
 */
final class StructuredEvaluationJsonSchema
{
    public const string OBJECT_SCHEMA_NAME = 'text_to_structured_evaluation';

    public static function objectSchema(): ObjectSchema
    {
        return new ObjectSchema([], name: self::OBJECT_SCHEMA_NAME);
    }
}
