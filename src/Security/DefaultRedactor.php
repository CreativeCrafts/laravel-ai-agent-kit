<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Security;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\Redactor;

final class DefaultRedactor implements Redactor
{
    private const string REDACTED_KEY = '[redacted-key]';

    public function redactText(string $value): string
    {
        return sprintf('[redacted:%d]', strlen($value));
    }

    /**
     * @param array<string, mixed> $values
     * @return list<string>
     */
    public function redactKeys(array $values): array
    {
        $keys = [];

        foreach (array_keys($values) as $key) {
            if ($key === '') {
                continue;
            }

            $normalized = $this->isSensitiveKey($key)
              ? self::REDACTED_KEY
              : $key;

            if (in_array($normalized, $keys, true)) {
                continue;
            }

            $keys[] = $normalized;
        }

        return $keys;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9]+/', '', $key) ?? $key);

        foreach (
          [
            'password',
            'passwd',
            'token',
            'secret',
            'apikey',
            'authorization',
            'authheader',
            'cookie',
            'session',
            'creditcard',
            'cvv',
            'ssn',
            'email',
            'phone',
          ] as $fragment
        ) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
