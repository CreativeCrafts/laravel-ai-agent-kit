<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Security;

use InvalidArgumentException;

final class SafeHttpUrlValidator
{
    /**
     * Reject HTTP(S) URLs that target private, link-local, or otherwise unsafe hosts.
     */
    public static function assertPublicHttpUrl(string $url, string $context): void
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            throw new InvalidArgumentException(sprintf('%s requires a URL with a host.', $context));
        }

        $normalizedHost = strtolower($host);

        if (str_starts_with($normalizedHost, '[') && str_ends_with($normalizedHost, ']')) {
            $normalizedHost = substr($normalizedHost, 1, -1);
        }

        foreach (['localhost', '127.0.0.1', '0.0.0.0', '::1', 'metadata.google.internal'] as $blockedHost) {
            if ($normalizedHost === $blockedHost) {
                throw new InvalidArgumentException(sprintf('%s rejects localhost and metadata hosts.', $context));
            }
        }

        foreach (['.local', '.internal', '.localhost', '.localdomain'] as $suffix) {
            if (str_ends_with($normalizedHost, $suffix)) {
                throw new InvalidArgumentException(sprintf('%s rejects internal host suffixes.', $context));
            }
        }

        if (filter_var($normalizedHost, FILTER_VALIDATE_IP) !== false
            && filter_var(
                $normalizedHost,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) === false) {
            throw new InvalidArgumentException(sprintf('%s rejects private or reserved IP addresses.', $context));
        }
    }
}
