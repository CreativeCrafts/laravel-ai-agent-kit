<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Security;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\DnsResolver;
use InvalidArgumentException;

final class SafeHttpUrlValidator
{
    /**
     * Reject HTTP(S) URLs that target private, link-local, or otherwise unsafe hosts.
     *
     * @param list<string> $allowedHosts When non-empty, the URL host must match the configured policy.
     */
    public static function assertPublicHttpUrl(
        string $url,
        string $context,
        array $allowedHosts = [],
        bool $requireHttps = false,
        MediaHostMatchMode $hostMatchMode = MediaHostMatchMode::ExactAndSubdomains,
        ?DnsResolver $dnsResolver = null,
    ): void {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if ($requireHttps && strtolower(is_string($scheme) ? $scheme : '') !== 'https') {
            throw new InvalidArgumentException(sprintf('%s requires an HTTPS URL.', $context));
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            throw new InvalidArgumentException(sprintf('%s requires a URL with a host.', $context));
        }

        $normalizedHost = self::normalizeHost($host);

        self::assertHostNotBlocked($normalizedHost, $context);
        self::assertHostAllowed($normalizedHost, $allowedHosts, $hostMatchMode, $context);

        if (filter_var($normalizedHost, FILTER_VALIDATE_IP) !== false) {
            self::assertPublicIp($normalizedHost, $context);

            return;
        }

        self::assertNotObfuscatedIpLiteral($normalizedHost, $context);
        self::assertResolvedAddressesArePublic(
            $normalizedHost,
            $context,
            $dnsResolver ?? new SystemDnsResolver(),
        );
    }

    private static function normalizeHost(string $host): string
    {
        $normalizedHost = strtolower($host);

        if (str_starts_with($normalizedHost, '[') && str_ends_with($normalizedHost, ']')) {
            return substr($normalizedHost, 1, -1);
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
    private static function assertHostAllowed(
        string $normalizedHost,
        array $allowedHosts,
        MediaHostMatchMode $hostMatchMode,
        string $context,
    ): void {
        if ($allowedHosts === []) {
            return;
        }

        foreach ($allowedHosts as $allowedHost) {
            $allowed = strtolower(trim($allowedHost));
            if ($allowed === '') {
                continue;
            }
            if (str_contains($allowed, '://')) {
                continue;
            }

            if ($normalizedHost === $allowed) {
                return;
            }

            if (
                $hostMatchMode === MediaHostMatchMode::ExactAndSubdomains
                && str_ends_with($normalizedHost, '.'.$allowed)
            ) {
                return;
            }
        }

        throw new InvalidArgumentException(sprintf('%s host is not in the configured URL allowlist.', $context));
    }

    /**
     * Reject obfuscated IP encodings that the OS resolver (and most HTTP clients) would
     * still interpret as an address — e.g. the loopback bypasses `http://2130706433/`
     * (decimal), `http://0x7f000001/` (hex), `http://017700000001/` (octal), and short
     * dotted forms such as `127.1`. Standard dotted-quad IPv4/IPv6 are already handled by
     * the FILTER_VALIDATE_IP branch before this check runs, and legitimate public
     * hostnames always contain a non-numeric TLD label, so any host whose every label is
     * purely numeric/hex is an address encoding rather than a DNS name.
     */
    private static function assertNotObfuscatedIpLiteral(string $normalizedHost, string $context): void
    {
        $candidate = rtrim($normalizedHost, '.');

        if ($candidate === '') {
            return;
        }

        foreach (explode('.', $candidate) as $label) {
            if (preg_match('/^(0x[0-9a-f]+|\d+)$/', $label) !== 1) {
                return;
            }
        }

        throw new InvalidArgumentException(
            sprintf('%s rejects numeric or obfuscated IP-literal hosts.', $context),
        );
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

    private static function assertResolvedAddressesArePublic(
        string $normalizedHost,
        string $context,
        DnsResolver $dnsResolver,
    ): void {
        $addresses = $dnsResolver->resolve($normalizedHost);

        if ($addresses === []) {
            return;
        }

        foreach ($addresses as $address) {
            self::assertPublicIp($address, $context);
        }
    }

}
