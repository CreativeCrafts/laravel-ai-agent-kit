<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Prompts;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Prompts\PromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions\PromptNotFoundException;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final readonly class FilePromptRepository implements PromptRepository
{
    /**
     * @var array<string, array<string, PromptTemplate>>
     */
    private array $templates;

    public function __construct(private string $rootPath)
    {
        $this->templates = $this->discoverTemplates();
    }

    public function has(string $name, ?string $version = null): bool
    {
        try {
            $this->get($name, $version);

            return true;
        } catch (PromptNotFoundException) {
            return false;
        }
    }

    public function get(string $name, ?string $version = null): PromptTemplate
    {
        $versions = $this->templates[$name] ?? null;

        if ($versions === null || $versions === []) {
            throw PromptNotFoundException::forName($name, $version);
        }

        if ($version !== null) {
            return $versions[$version] ?? throw PromptNotFoundException::forName($name, $version);
        }

        $resolvedVersion = $this->resolveLatestVersion(array_keys($versions));

        return $versions[$resolvedVersion] ?? throw PromptNotFoundException::forName($name);
    }

    /**
     * @param array<string, scalar|null> $variables
     */
    public function render(string $name, array $variables = [], ?string $version = null): string
    {
        return $this->get($name, $version)->render($variables);
    }

    /**
     * @return array<string, array<string, PromptTemplate>>
     */
    private function discoverTemplates(): array
    {
        if (!is_dir($this->rootPath)) {
            return [];
        }

        $templates = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->rootPath, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo) {
                continue;
            }
            if (!$file->isFile()) {
                continue;
            }
            if ($file->getFilename() !== 'metadata.php') {
                continue;
            }

            $metadataPath = $file->getPathname();
            $metadata = $this->loadMetadata($metadataPath);
            $promptName = $this->resolvePromptName($metadata, dirname($metadataPath));

            if ($promptName === '') {
                continue;
            }

            $versions = $metadata['versions'] ?? null;

            if (!is_array($versions)) {
                continue;
            }

            foreach ($versions as $version => $details) {
                if (!is_string($version)) {
                    continue;
                }
                if ($version === '') {
                    continue;
                }
                if (!is_array($details)) {
                    continue;
                }
                $templateFile = $details['template'] ?? ($version . '.md');
                if (!is_string($templateFile)) {
                    continue;
                }
                if ($templateFile === '') {
                    continue;
                }

                $templatePath = dirname($metadataPath) . DIRECTORY_SEPARATOR . $templateFile;

                if (!is_file($templatePath)) {
                    continue;
                }

                $content = file_get_contents($templatePath);

                if (!is_string($content)) {
                    continue;
                }

                $templates[$promptName][$version] = PromptTemplate::fromContent(
                    name: $promptName,
                    version: $version,
                    content: $content,
                );
            }
        }

        return $templates;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadMetadata(string $metadataPath): array
    {
        $metadata = include $metadataPath;

        if (!is_array($metadata)) {
            return [];
        }

        $normalized = [];

        foreach ($metadata as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function resolvePromptName(array $metadata, string $promptDirectory): string
    {
        $name = $metadata['name'] ?? null;

        if (is_string($name) && $name !== '') {
            return $name;
        }

        $relativeDirectory = ltrim(str_replace($this->rootPath, '', $promptDirectory), DIRECTORY_SEPARATOR);

        return str_replace(DIRECTORY_SEPARATOR, '.', $relativeDirectory);
    }

    /**
     * @param list<string> $versions
     */
    private function resolveLatestVersion(array $versions): string
    {
        usort($versions, static function (string $left, string $right): int {
            return version_compare($right, $left);
        });

        return $versions[0];
    }
}
