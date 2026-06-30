<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Security;

use InvalidArgumentException;

final class SafeLocalPathReferenceValidator
{
    /**
     * Reject path or storage references that could escape their intended root.
     */
    public static function assertSafeReference(string $reference, string $context): void
    {
        if (str_contains($reference, "\0")) {
            throw new InvalidArgumentException(sprintf('%s must not contain null bytes.', $context));
        }

        if (preg_match('/^\s*file:/i', $reference) === 1) {
            throw new InvalidArgumentException(sprintf('%s must not use file:// references.', $context));
        }

        $normalized = str_replace('\\', '/', $reference);

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '..') {
                throw new InvalidArgumentException(sprintf('%s must not contain parent-directory segments.', $context));
            }
        }
    }
}
