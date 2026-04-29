<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * Maps SDK agent responses to {@see ExecutionResult::$structuredOutput}.
 *
 * Extracted for unit testing without faking the full {@see SdkAiRuntime} stack.
 */
final class StructuredAgentResponseMapper
{
    /**
     * @return array<string, mixed>|null
     */
    public static function mapStructuredPayload(AgentResponse $response): ?array
    {
        if (!$response instanceof StructuredAgentResponse) {
            return null;
        }

        $structured = [];

        foreach ($response->structured as $key => $value) {
            if (is_string($key)) {
                $structured[$key] = $value;
            }
        }

        return $structured;
    }
}
