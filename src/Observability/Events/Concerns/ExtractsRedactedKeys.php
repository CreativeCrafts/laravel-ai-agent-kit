<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events\Concerns;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\Redactor;

trait ExtractsRedactedKeys
{
    /**
     * @param array<string, mixed> $values
     * @return list<string>
     */
    private static function keys(array $values, ?Redactor $redactor = null): array
    {
        if ($redactor instanceof Redactor) {
            return $redactor->redactKeys($values);
        }

        return array_values(
            array_filter(
                array_keys($values),
                static fn (string $key): bool => $key !== '',
            ),
        );
    }
}
