<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions;

use RuntimeException;
use Throwable;

final class ProviderCapabilityConformanceException extends RuntimeException
{
    public static function forUnknownCapability(string $capability): self
    {
        return new self(
            sprintf(
                'Audited provider capability [%s] is not defined.',
                $capability,
            ),
        );
    }

    /**
     * @param list<string> $missingCapabilities
     */
    public static function forProfileMismatch(
        string $capability,
        string $providerProfile,
        array $missingCapabilities,
    ): self {
        return new self(
            sprintf(
                'Provider profile [%s] does not conform to audited capability [%s]; missing declared capabilities [%s].',
                $providerProfile,
                $capability,
                implode(', ', $missingCapabilities),
            ),
        );
    }

    /**
     * @param array<string, string> $profilesByStage
     * @param array<string, list<string>> $missingCapabilitiesByStage
     */
    public static function forStageMismatch(
        string $capability,
        array $profilesByStage,
        array $missingCapabilitiesByStage,
    ): self {
        return new self(
            sprintf(
                'Staged provider profiles [%s] do not conform to audited capability [%s]; missing declared capabilities [%s].',
                self::formatProfilesByStage($profilesByStage),
                $capability,
                self::formatMissingByStage($missingCapabilitiesByStage),
            ),
        );
    }

    public static function forProfileProbeFailure(
        string $capability,
        string $providerProfile,
        Throwable $previous,
    ): self {
        return new self(
            sprintf(
                'Provider profile [%s] declared audited capability [%s] but the deterministic conformance probe failed.',
                $providerProfile,
                $capability,
            ),
            previous: $previous,
        );
    }

    /**
     * @param array<string, string> $profilesByStage
     */
    public static function forStageProbeFailure(
        string $capability,
        array $profilesByStage,
        Throwable $previous,
    ): self {
        return new self(
            sprintf(
                'Staged provider profiles [%s] declared audited capability [%s] but the deterministic conformance probe failed.',
                self::formatProfilesByStage($profilesByStage),
                $capability,
            ),
            previous: $previous,
        );
    }

    /**
     * @param array<string, string> $profilesByStage
     */
    private static function formatProfilesByStage(array $profilesByStage): string
    {
        $pairs = [];

        foreach ($profilesByStage as $stage => $profile) {
            $pairs[] = sprintf('%s=%s', $stage, $profile);
        }

        return implode(', ', $pairs);
    }

    /**
     * @param array<string, list<string>> $missingCapabilitiesByStage
     */
    private static function formatMissingByStage(array $missingCapabilitiesByStage): string
    {
        $parts = [];

        foreach ($missingCapabilitiesByStage as $stage => $missingCapabilities) {
            $parts[] = sprintf(
                '%s: %s',
                $stage,
                implode(', ', $missingCapabilities),
            );
        }

        return implode('; ', $parts);
    }
}
