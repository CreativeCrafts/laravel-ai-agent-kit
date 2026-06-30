<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Security;

final class MediaInputSecurityConfig
{
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
