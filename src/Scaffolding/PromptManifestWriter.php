<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Scaffolding;

use RuntimeException;
use Throwable;

/** Reads and atomically writes prompt metadata without interpolating untrusted PHP source. */
final class PromptManifestWriter
{
    /**
     * Load prompt metadata while preserving package-unknown string fields.
     *
     * @return array<string, mixed>
     */
    public function read(string $metadataPath): array
    {
        try {
            ob_start();

            try {
                $metadata = include $metadataPath;
                $output = ob_get_contents();
            } finally {
                ob_end_clean();
            }
        } catch (Throwable $throwable) {
            throw new RuntimeException("Prompt manifest [{$metadataPath}] could not be loaded: {$throwable->getMessage()}", $throwable->getCode(), previous: $throwable);
        }

        if (!is_string($output) || $output !== '') {
            throw new RuntimeException("Prompt manifest [{$metadataPath}] must not emit output while loading.");
        }

        if (!is_array($metadata)) {
            throw new RuntimeException("Prompt manifest [{$metadataPath}] must return an array.");
        }

        $normalized = [];

        foreach ($metadata as $key => $value) {
            if (!is_string($key)) {
                throw new RuntimeException("Prompt manifest [{$metadataPath}] must use string top-level keys.");
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * Atomically write a complete prompt manifest using structural PHP serialization.
     *
     * @param array<string, mixed> $metadata
     */
    public function write(string $metadataPath, array $metadata): void
    {
        $exportedMetadata = var_export($metadata, true);
        $contents = "<?php\n\ndeclare(strict_types=1);\n\nreturn {$exportedMetadata};\n";
        $temporaryPath = tempnam(dirname($metadataPath), '.prompt-metadata-');

        if (!is_string($temporaryPath)) {
            throw new RuntimeException("Unable to create a temporary prompt manifest beside [{$metadataPath}].");
        }

        try {
            if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
                throw new RuntimeException("Unable to write temporary prompt manifest [{$temporaryPath}].");
            }

            if (!rename($temporaryPath, $metadataPath)) {
                throw new RuntimeException("Unable to replace prompt manifest [{$metadataPath}] atomically.");
            }
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
}
