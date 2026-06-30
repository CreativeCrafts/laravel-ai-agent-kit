<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Security;

final class MediaSourceSafeMetadata
{
    /**
     * @return array<string, mixed>
     */
    public static function referenceFields(string $reference, bool $isUrl): array
    {
        if ($isUrl) {
            $fields = [];

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

        $normalized = str_replace('\\', '/', $reference);

        return [
            'reference_basename' => basename($normalized),
            'reference_fingerprint' => hash('sha256', $reference),
        ];
    }
}
