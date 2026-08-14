<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Prompts;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Prompts\PromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions\InvalidPromptManifestException;
use CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions\PromptNotFoundException;
use CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions\UndeclaredPromptVariableException;
use CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions\UnusedPromptVariableDeclarationException;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

final readonly class FilePromptRepository implements PromptRepository
{
    /**
     * @var array<string, array<string, PromptTemplate>>
     */
    private array $templates;

    /**
     * @var array<string, PromptManifest>
     */
    private array $manifests;

    public function __construct(private string $rootPath)
    {
        $discoveredPrompts = $this->discoverPrompts();
        $this->templates = $discoveredPrompts['templates'];
        $this->manifests = $discoveredPrompts['manifests'];
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

        $currentVersion = $this->manifests[$name]->currentVersion ?? null;
        $resolvedVersion = $currentVersion ?? $this->resolveLatestVersion(array_keys($versions));

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
     * @return array{
     *     templates: array<string, array<string, PromptTemplate>>,
     *     manifests: array<string, PromptManifest>
     * }
     */
    private function discoverPrompts(): array
    {
        if (!is_dir($this->rootPath)) {
            return [
              'templates' => [],
              'manifests' => [],
            ];
        }

        $templates = [];
        $manifests = [];
        $metadataPaths = [];
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

            $metadataPaths[] = $file->getPathname();
        }

        sort($metadataPaths);

        foreach ($metadataPaths as $metadataPath) {
            $metadata = $this->loadMetadata($metadataPath);
            $manifest = PromptManifest::fromMetadata(
                metadata: $metadata,
                fallbackName: $this->resolveFallbackPromptName(dirname($metadataPath)),
                metadataPath: $metadataPath,
            );

            if (isset($manifests[$manifest->name])) {
                throw InvalidPromptManifestException::forField(
                    $metadataPath,
                    'name',
                    "duplicates the already discovered prompt [{$manifest->name}]",
                );
            }

            $resolvedVersions = [];

            foreach ($manifest->versions as $definition) {
                $templatePath = dirname($metadataPath) . DIRECTORY_SEPARATOR . $definition->templateFile;

                if (!is_file($templatePath) || !is_readable($templatePath)) {
                    throw InvalidPromptManifestException::forMissingTemplate(
                        $manifest->name,
                        $definition->version,
                        $templatePath,
                    );
                }

                $content = file_get_contents($templatePath);

                if (!is_string($content)) {
                    throw InvalidPromptManifestException::forMissingTemplate(
                        $manifest->name,
                        $definition->version,
                        $templatePath,
                    );
                }

                $resolvedVersions[$definition->version] = $this->createTemplate(
                    manifest: $manifest,
                    definition: $definition,
                    content: $content,
                );
            }

            $templates[$manifest->name] = $resolvedVersions;
            $manifests[$manifest->name] = $manifest;
        }

        return [
          'templates' => $templates,
          'manifests' => $manifests,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadMetadata(string $metadataPath): array
    {
        try {
            $metadata = include $metadataPath;
        } catch (Throwable $throwable) {
            throw InvalidPromptManifestException::forLoadFailure($metadataPath, $throwable);
        }

        if (!is_array($metadata)) {
            throw InvalidPromptManifestException::forField(
                $metadataPath,
                'return',
                'must be an array',
            );
        }

        $normalized = [];

        foreach ($metadata as $key => $value) {
            if (!is_string($key)) {
                throw InvalidPromptManifestException::forField(
                    $metadataPath,
                    'return',
                    'must use string keys',
                );
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private function resolveFallbackPromptName(string $promptDirectory): string
    {
        $relativeDirectory = ltrim(str_replace($this->rootPath, '', $promptDirectory), DIRECTORY_SEPARATOR);

        return str_replace(DIRECTORY_SEPARATOR, '.', $relativeDirectory);
    }

    private function createTemplate(
        PromptManifest $manifest,
        PromptVersionDefinition $definition,
        string $content,
    ): PromptTemplate {
        $parsedTemplate = (new PromptTemplateParser())->parse($content);
        $declaredVariables = $definition->variables;

        if ($declaredVariables === null) {
            return new PromptTemplate(
                name: $manifest->name,
                version: $definition->version,
                content: $content,
                variables: $parsedTemplate->variables,
            );
        }

        $undeclaredVariables = array_values(array_diff($parsedTemplate->variables, $declaredVariables));

        if ($undeclaredVariables !== []) {
            throw UndeclaredPromptVariableException::forTemplate(
                $manifest->name,
                $definition->version,
                $undeclaredVariables,
            );
        }

        $unusedVariables = array_values(array_diff($declaredVariables, $parsedTemplate->variables));

        if ($unusedVariables !== []) {
            throw UnusedPromptVariableDeclarationException::forTemplate(
                $manifest->name,
                $definition->version,
                $unusedVariables,
            );
        }

        return new PromptTemplate(
            name: $manifest->name,
            version: $definition->version,
            content: $content,
            variables: $declaredVariables,
        );
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
