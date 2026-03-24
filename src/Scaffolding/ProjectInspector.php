<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Scaffolding;

use JsonException;

final readonly class ProjectInspector
{
    public function __construct(
        private string $basePath,
    ) {
    }

    /**
     * @throws JsonException
     */
    public function inspect(): ProjectContext
    {
        $composerJson = $this->readJsonFile($this->path('composer.json'));
        $composerLock = $this->readJsonFile($this->path('composer.lock'));
        $autoloadRoots = $this->autoloadRoots($composerJson);
        [$rootNamespace, $sourceRelativePath] = $this->primaryAutoloadRoot($autoloadRoots);

        $hasBootstrapApp = is_file($this->path('bootstrap/app.php'));

        if ($sourceRelativePath === null) {
            $sourceRelativePath = $hasBootstrapApp ? 'app/' : 'src/';
        }

        $projectType = $this->detectProjectType(
            composerJson: $composerJson,
            sourceRelativePath: $sourceRelativePath,
            hasBootstrapApp: $hasBootstrapApp,
        );

        return new ProjectContext(
            basePath: rtrim($this->basePath, DIRECTORY_SEPARATOR),
            projectType: $projectType,
            hasComposerJson: $composerJson !== [],
            hasComposerLock: $composerLock !== [],
            rootNamespace: $rootNamespace,
            laravelVersion: $this->detectLaravelVersion($composerLock, $composerJson),
            hasLaravelAiSdk: $this->composerDeclaresPackage($composerJson, 'laravel/ai')
          || $this->composerLockContainsPackage($composerLock, 'laravel/ai'),
            sourceDirectory: $this->path($sourceRelativePath),
            promptsDirectory: $this->path('resources/prompts'),
            autoloadRoots: $autoloadRoots,
        );
    }

    /**
     * @return array<string, mixed>
     * @throws JsonException
     */
    private function readJsonFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if (!is_string($contents) || trim($contents) === '') {
            return [];
        }

        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            return [];
        }

        return $this->normalizeAssocArray($decoded);
    }

    /**
     * @param array<mixed> $values
     * @return array<string, mixed>
     */
    private function normalizeAssocArray(array $values): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private function path(string $relativePath): string
    {
        $normalized = str_replace('\\', '/', $relativePath);
        $normalized = trim($normalized, '/');

        $basePath = rtrim($this->basePath, DIRECTORY_SEPARATOR);

        if ($normalized === '') {
            return $basePath;
        }

        return $basePath . '/' . $normalized;
    }

    /**
     * @param array<string, mixed> $composerJson
     * @return array<string, string>
     */
    private function autoloadRoots(array $composerJson): array
    {
        $autoload = $composerJson['autoload'] ?? null;

        if (!is_array($autoload)) {
            return [];
        }

        $psr4 = $autoload['psr-4'] ?? null;

        if (!is_array($psr4)) {
            return [];
        }

        $roots = [];

        foreach ($psr4 as $namespace => $paths) {
            if (!is_string($namespace)) {
                continue;
            }
            if ($namespace === '') {
                continue;
            }
            $path = is_string($paths) ? $paths : $this->firstStringPath($paths);
            if ($path === null) {
                continue;
            }
            if (trim($path) === '') {
                continue;
            }

            $roots[$namespace] = $this->normalizeRelativePath($path);
        }

        return $roots;
    }

    private function firstStringPath(mixed $paths): ?string
    {
        if (!is_array($paths)) {
            return null;
        }

        foreach ($paths as $path) {
            if (is_string($path) && trim($path) !== '') {
                return $path;
            }
        }

        return null;
    }

    private function normalizeRelativePath(string $path): string
    {
        $normalized = str_replace('\\', '/', trim($path));
        $normalized = ltrim($normalized, './');
        $normalized = trim($normalized, '/');

        return $normalized === ''
          ? ''
          : $normalized . '/';
    }

    /**
     * @param array<string, string> $autoloadRoots
     * @return array{0: ?string, 1: ?string}
     */
    private function primaryAutoloadRoot(array $autoloadRoots): array
    {
        foreach ($autoloadRoots as $namespace => $path) {
            if ($namespace === 'App\\' || $path === 'app/') {
                return [$namespace, $path];
            }
        }

        foreach ($autoloadRoots as $namespace => $path) {
            if ($path === 'src/') {
                return [$namespace, $path];
            }
        }

        $firstNamespace = array_key_first($autoloadRoots);

        if (!is_string($firstNamespace)) {
            return [null, null];
        }

        return [$firstNamespace, $autoloadRoots[$firstNamespace]];
    }

    /**
     * @param array<string, mixed> $composerJson
     * @return 'laravel_app'|'laravel_package'|'package'|'unknown'
     */
    private function detectProjectType(array $composerJson, string $sourceRelativePath, bool $hasBootstrapApp): string
    {
        $extra = $composerJson['extra'] ?? null;
        $laravel = is_array($extra) ? ($extra['laravel'] ?? null) : null;
        $providers = is_array($laravel) ? ($laravel['providers'] ?? null) : null;

        if (is_array($providers)) {
            return 'laravel_package';
        }

        $type = $composerJson['type'] ?? null;

        if ($type === 'library') {
            return 'package';
        }

        if ($sourceRelativePath === 'app/' || $this->composerRequiresPackage($composerJson, 'laravel/framework')) {
            return 'laravel_app';
        }

        if ($hasBootstrapApp && $sourceRelativePath !== 'src/') {
            return 'laravel_app';
        }

        return 'unknown';
    }

    /**
     * @param array<string, mixed> $composerJson
     */
    private function composerRequiresPackage(array $composerJson, string $packageName): bool
    {
        $require = $composerJson['require'] ?? null;

        if (!is_array($require)) {
            return false;
        }

        $constraint = $require[$packageName] ?? null;

        return is_string($constraint) && trim($constraint) !== '';
    }

    /**
     * @param array<string, mixed> $composerLock
     * @param array<string, mixed> $composerJson
     */
    private function detectLaravelVersion(array $composerLock, array $composerJson): ?string
    {
        $lockedVersion = $this->packageVersionFromLock($composerLock, 'laravel/framework');

        if ($lockedVersion !== null) {
            return $lockedVersion;
        }

        return $this->packageConstraintFromComposer($composerJson, 'laravel/framework');
    }

    /**
     * @param array<string, mixed> $composerLock
     */
    private function packageVersionFromLock(array $composerLock, string $packageName): ?string
    {
        foreach (['packages', 'packages-dev'] as $section) {
            $packages = $composerLock[$section] ?? null;

            if (!is_array($packages)) {
                continue;
            }

            foreach ($packages as $package) {
                if (!is_array($package)) {
                    continue;
                }

                $name = $package['name'] ?? null;
                $version = $package['version'] ?? null;

                if ($name === $packageName && is_string($version) && $version !== '') {
                    return ltrim($version, 'v');
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $composerJson
     */
    private function packageConstraintFromComposer(array $composerJson, string $packageName): ?string
    {
        foreach (['require', 'require-dev'] as $section) {
            $dependencies = $composerJson[$section] ?? null;

            if (!is_array($dependencies)) {
                continue;
            }

            $constraint = $dependencies[$packageName] ?? null;

            if (is_string($constraint) && trim($constraint) !== '') {
                return trim($constraint);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $composerJson
     */
    private function composerDeclaresPackage(array $composerJson, string $packageName): bool
    {
        return $this->packageConstraintFromComposer($composerJson, $packageName) !== null;
    }

    /**
     * @param array<string, mixed> $composerLock
     */
    private function composerLockContainsPackage(array $composerLock, string $packageName): bool
    {
        return $this->packageVersionFromLock($composerLock, $packageName) !== null;
    }
}
