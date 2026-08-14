<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Security;

final class MediaInputSecurityConfig
{
    public static function requireHttps(): bool
    {
        if (!function_exists('config')) {
            return false;
        }

        return config('ai-agent-kit.media_input.require_https', false) === true;
    }

    public static function hostMatchMode(): MediaHostMatchMode
    {
        if (!function_exists('config')) {
            return MediaHostMatchMode::ExactAndSubdomains;
        }

        $configured = config(
            'ai-agent-kit.media_input.host_match',
            MediaHostMatchMode::ExactAndSubdomains->value,
        );

        if (!is_string($configured)) {
            return MediaHostMatchMode::ExactAndSubdomains;
        }

        return MediaHostMatchMode::tryFrom($configured)
            ?? MediaHostMatchMode::ExactAndSubdomains;
    }

    public static function includeDiagnosticNames(): bool
    {
        if (!function_exists('config')) {
            return false;
        }

        return config('ai-agent-kit.media_input.include_diagnostic_names', false) === true;
    }

    /**
     * @return list<string>
     */
    public static function urlAllowedHosts(): array
    {
        if (!function_exists('config')) {
            return [];
        }

        $configured = config('ai-agent-kit.media_input.url_allowed_hosts', []);

        if (!is_array($configured)) {
            return [];
        }

        $hosts = [];

        foreach ($configured as $host) {
            if (!is_string($host)) {
                continue;
            }

            $trimmed = trim($host);

            if ($trimmed === '') {
                continue;
            }

            $hosts[] = $trimmed;
        }

        return $hosts;
    }
}
