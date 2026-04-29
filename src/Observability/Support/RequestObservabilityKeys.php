<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Support;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\Redactor;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\Concerns\ExtractsRedactedKeys;

/**
 * Exposes {@see ExtractsRedactedKeys} for callers that are not event classes.
 */
final class RequestObservabilityKeys
{
    use ExtractsRedactedKeys;

    /**
     * @return list<string>
     */
    public static function inputKeys(ExecutionRequest $request, ?Redactor $redactor = null): array
    {
        return self::keys($request->input, $redactor);
    }

    /**
     * @return list<string>
     */
    public static function metadataKeys(ExecutionRequest $request, ?Redactor $redactor = null): array
    {
        return self::keys($request->metadata, $redactor);
    }
}
