<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Prompts;

use CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions\DuplicatePromptVariableDeclarationException;
use CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions\InvalidPromptManifestException;
use CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions\InvalidPromptVariableNameException;

/**
 * Validated package-owned representation of one file prompt manifest.
 *
 * @internal
 */
final readonly class PromptManifest
{
    /**
     * @param array<string, PromptVersionDefinition> $versions
     */
    private function __construct(
        public string $name,
        public ?string $currentVersion,
        public array $versions,
        public string $metadataPath,
    ) {
    }

    /**
     * Build and validate a manifest from loaded metadata.
     *
     * @param array<string, mixed> $metadata
     */
    public static function fromMetadata(
        array $metadata,
        string $fallbackName,
        string $metadataPath,
    ): self {
        $name = self::resolveName($metadata, $fallbackName, $metadataPath);
        $versionMetadata = $metadata['versions'] ?? null;

        if (!is_array($versionMetadata) || $versionMetadata === []) {
            throw InvalidPromptManifestException::forField(
                $metadataPath,
                'versions',
                'must be a non-empty array',
            );
        }

        $versions = [];

        foreach ($versionMetadata as $version => $details) {
            if (!is_string($version) || $version === '') {
                throw InvalidPromptManifestException::forField(
                    $metadataPath,
                    'versions',
                    'must use non-empty string version keys',
                );
            }

            if (!is_array($details)) {
                throw InvalidPromptManifestException::forField(
                    $metadataPath,
                    "versions.{$version}",
                    'must be an array',
                );
            }

            $details = self::normalizeVersionDetails($details, $version, $metadataPath);
            $templateFile = self::resolveTemplateFile($details, $version, $metadataPath);
            $variables = self::resolveVariables($details, $name, $version, $metadataPath);
            $description = self::resolveDescription($details, $version, $metadataPath);

            $versions[$version] = new PromptVersionDefinition(
                version: $version,
                templateFile: $templateFile,
                variables: $variables,
                description: $description,
            );
        }

        $currentVersion = self::resolveCurrentVersion($metadata, $versions, $metadataPath);

        return new self($name, $currentVersion, $versions, $metadataPath);
    }

    /**
     * @param array<mixed, mixed> $details
     * @return array<string, mixed>
     */
    private static function normalizeVersionDetails(
        array $details,
        string $version,
        string $metadataPath,
    ): array {
        $normalized = [];

        foreach ($details as $key => $value) {
            if (!is_string($key)) {
                throw InvalidPromptManifestException::forField(
                    $metadataPath,
                    "versions.{$version}",
                    'must use string field names',
                );
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /** @param array<string, mixed> $metadata */
    private static function resolveName(array $metadata, string $fallbackName, string $metadataPath): string
    {
        if (!array_key_exists('name', $metadata)) {
            if ($fallbackName !== '') {
                return $fallbackName;
            }

            throw InvalidPromptManifestException::forField(
                $metadataPath,
                'name',
                'must be a non-empty string when it cannot be inferred from the directory',
            );
        }

        $name = $metadata['name'];

        if (!is_string($name) || $name === '') {
            throw InvalidPromptManifestException::forField(
                $metadataPath,
                'name',
                'must be a non-empty string',
            );
        }

        return $name;
    }

    /**
     * @param array<string, mixed> $details
     */
    private static function resolveTemplateFile(array $details, string $version, string $metadataPath): string
    {
        $templateFile = $details['template'] ?? $version . '.md';

        if (!is_string($templateFile) || $templateFile === '') {
            throw InvalidPromptManifestException::forField(
                $metadataPath,
                "versions.{$version}.template",
                'must be a non-empty string',
            );
        }

        if (!self::isSafeTemplatePath($templateFile)) {
            throw InvalidPromptManifestException::forField(
                $metadataPath,
                "versions.{$version}.template",
                'must be a safe relative path without traversal or control characters',
            );
        }

        return $templateFile;
    }

    /** Determine whether a template reference stays within its prompt directory. */
    private static function isSafeTemplatePath(string $templateFile): bool
    {
        if (
            preg_match('/[\x00-\x1F\x7F]/', $templateFile) === 1
            || str_starts_with($templateFile, '/')
            || str_starts_with($templateFile, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $templateFile) === 1
        ) {
            return false;
        }

        $segments = explode('/', str_replace('\\', '/', $templateFile));

        foreach ($segments as $segment) {
            if (in_array($segment, ['', '.', '..'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $details
     * @return list<string>|null
     */
    private static function resolveVariables(
        array $details,
        string $name,
        string $version,
        string $metadataPath,
    ): ?array {
        if (!array_key_exists('variables', $details)) {
            return null;
        }

        $variableMetadata = $details['variables'];

        if (!is_array($variableMetadata) || !array_is_list($variableMetadata)) {
            throw InvalidPromptManifestException::forField(
                $metadataPath,
                "versions.{$version}.variables",
                'must be a list of unique supported variable names',
            );
        }

        $variables = [];
        $seenVariables = [];

        foreach ($variableMetadata as $index => $variable) {
            if (!is_string($variable) || !PromptTemplateParser::supportsVariableName($variable)) {
                throw InvalidPromptVariableNameException::forDeclaration($name, $version, $index);
            }

            if (isset($seenVariables[$variable])) {
                throw DuplicatePromptVariableDeclarationException::forDeclaration($name, $version, $variable);
            }

            $variables[] = $variable;
            $seenVariables[$variable] = true;
        }

        return $variables;
    }

    /**
     * @param array<string, mixed> $details
     */
    private static function resolveDescription(array $details, string $version, string $metadataPath): ?string
    {
        if (!array_key_exists('description', $details)) {
            return null;
        }

        $description = $details['description'];

        if (!is_string($description)) {
            throw InvalidPromptManifestException::forField(
                $metadataPath,
                "versions.{$version}.description",
                'must be a string when present',
            );
        }

        return $description;
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, PromptVersionDefinition> $versions
     */
    private static function resolveCurrentVersion(
        array $metadata,
        array $versions,
        string $metadataPath,
    ): ?string {
        if (!array_key_exists('current_version', $metadata)) {
            return null;
        }

        $currentVersion = $metadata['current_version'];

        if (!is_string($currentVersion) || $currentVersion === '') {
            throw InvalidPromptManifestException::forField(
                $metadataPath,
                'current_version',
                'must be a non-empty registered version string',
            );
        }

        if (!isset($versions[$currentVersion])) {
            throw InvalidPromptManifestException::forUnregisteredCurrentVersion($metadataPath, $currentVersion);
        }

        return $currentVersion;
    }
}
