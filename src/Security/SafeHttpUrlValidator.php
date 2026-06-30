<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Security;

use InvalidArgumentException;

final class SafeHttpUrlValidator
{
    /**
     * Reject HTTP(S) URLs that target private, link-local, or otherwise unsafe hosts.
     *
     * @param list<string> $allowedHosts When non-empty, the URL host must match one entry exactly or as a subdomain.
     */
    public static function assertPublicHttpUrl(string $url, string $context, array $allowedHosts = []): void
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            throw new InvalidArgumentException(sprintf('%s requires a URL with a host.', $context));
        }

        $normalizedHost = self::normalizeHost($host);

        self::assertHostNotBlocked($normalizedHost, $context);
        self::assertHostAllowed($normalizedHost, $allowedHosts, $context);

        if (filter_var($normalizedHost, FILTER_VALIDATE_IP) !== false) {
            self::assertPublicIp($normalizedHost, $context);

            return;
        }

        self::assertResolvedAddressesArePublic($normalizedHost, $context);
    }

    private static function normalizeHost(string $host): string
    {
        $normalizedHost = strtolower($host);

        if (str_starts_with($normalizedHost, '[') && str_ends_with($normalizedHost, ']')) {
            $normalizedHost = substr($normalizedHost, 1, -1);
        }

        return $normalizedHost;
    }

    private static function assertHostNotBlocked(string $normalizedHost, string $context): void
    {
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
    }

    /**
     * @param list<string> $allowedHosts
     */
    private static function assertHostAllowed(string $normalizedHost, array $allowedHosts, string $context): void
    {
        if ($allowedHosts === []) {
            return;
        }

        foreach ($allowedHosts as $allowedHost) {
            $allowed = strtolower(trim($allowedHost));

            if ($allowed === '' || str_contains($allowed, '://')) {
                continue;
            }

            if ($normalizedHost === $allowed || str_ends_with($normalizedHost, '.'.$allowed)) {
                return;
            }
        }

        throw new InvalidArgumentException(sprintf('%s host is not in the configured URL allowlist.', $context));
    }

    private static function assertPublicIp(string $ip, string $context): void
    {
        if (filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false) {
            throw new InvalidArgumentException(sprintf('%s rejects private or reserved IP addresses.', $context));
        }
    }

    private static function assertResolvedAddressesArePublic(string $normalizedHost, string $context): void
    {
        $addresses = self::resolveHostAddresses($normalizedHost);

        if ($addresses === []) {
            return;
        }

        foreach ($addresses as $address) {
            self::assertPublicIp($address, $context);
        }
    }

    /**
     * @return list<string>
     */
    private static function resolveHostAddresses(string $host): array
    {
        $addresses = [];

        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_A + DNS_AAAA);

            if (is_array($records)) {
                foreach ($records as $record) {
                    if (isset($record['ip']) && is_string($record['ip']) && $record['ip'] !== '') {
                        $addresses[] = $record['ip'];
                    }

                    if (isset($record['ipv6']) && is_string($record['ipv6']) && $record['ipv6'] !== '') {
                        $addresses[] = $record['ipv6'];
                    }
                }
            }
        }

        if ($addresses === []) {
            $fallback = @gethostbynamel($host);

            if (is_array($fallback)) {
                foreach ($fallback as $address) {
                    if ($address !== '') {
                        $addresses[] = $address;
                    }
                }
            }
        }

        return array_values(array_unique($addresses));
    }
}
