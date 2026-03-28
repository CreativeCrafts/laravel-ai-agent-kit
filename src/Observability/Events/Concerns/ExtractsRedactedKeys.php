<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events\Concerns;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\Redactor;

trait ExtractsRedactedKeys
{
    /**
     * @param array<array-key, mixed> $values
     * @return list<string>
     */
    private static function keys(array $values, ?Redactor $redactor = null): array
    {
        $stringKeyedValues = self::stringKeyedValues($values);

        if ($redactor instanceof Redactor) {
            return $redactor->redactKeys($stringKeyedValues);
        }

        return array_keys($stringKeyedValues);
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array<string, mixed>
     */
    private static function stringKeyedValues(array $values): array
    {
        $stringKeyedValues = [];

        foreach ($values as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if ($key === '') {
                continue;
            }
            $stringKeyedValues[$key] = $value;
        }

        return $stringKeyedValues;
    }
}
