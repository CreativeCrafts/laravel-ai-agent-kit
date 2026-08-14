<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Security;

final class MediaSourceSafeMetadata
{
    /**
     * @return array<string, mixed>
     */
    public static function referenceFields(
        string $reference,
        bool $isUrl,
        bool $includeDiagnosticNames = false,
    ): array {
        if ($isUrl) {
            $fields = [
                'reference_fingerprint' => hash('sha256', $reference),
            ];

            $scheme = parse_url($reference, PHP_URL_SCHEME);
            if (is_string($scheme) && $scheme !== '') {
                $fields['url_scheme'] = $scheme;
            }

            $host = parse_url($reference, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $fields['url_host'] = $host;
            }

            return $fields;
        }

        $fields = [
            'reference_fingerprint' => hash('sha256', $reference),
        ];

        if ($includeDiagnosticNames) {
            $normalized = str_replace('\\', '/', $reference);
            $fields['reference_basename'] = basename($normalized);
        }

        return $fields;
    }
}
