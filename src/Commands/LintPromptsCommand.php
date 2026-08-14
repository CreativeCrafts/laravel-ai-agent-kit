<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Commands;

use CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions\InvalidPromptManifestException;
use CreativeCrafts\LaravelAiAgentKit\Prompts\FilePromptRepository;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class LintPromptsCommand extends Command
{
    protected $signature = 'ai:prompts:lint
        {--path= : Prompt root to validate instead of the configured file repository path}';

    protected $description = 'Validate prompt manifests, templates, variables, and current versions.';

    public function handle(): int
    {
        $rootPath = $this->resolveRootPath();

        if ($rootPath === null) {
            return self::FAILURE;
        }

        try {
            new FilePromptRepository($rootPath);
        } catch (InvalidPromptManifestException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Prompt manifests are valid in [{$rootPath}].");

        return self::SUCCESS;
    }

    /** Resolve the requested or configured prompt root. */
    private function resolveRootPath(): ?string
    {
        $requestedPath = $this->option('path');

        if ($requestedPath !== null) {
            if (!is_string($requestedPath) || trim($requestedPath) === '') {
                $this->components->error('The [--path] option must be a non-empty path when provided.');

                return null;
            }

            return $this->absolutePath(trim($requestedPath));
        }

        /** @var ConfigRepository $config */
        $config = $this->laravel->make(ConfigRepository::class);
        $configuredPath = $config->get('ai-agent-kit.prompts.file.root_path');

        if ($configuredPath === null) {
            return $this->laravel->basePath('resources/prompts');
        }

        if (!is_string($configuredPath) || trim($configuredPath) === '') {
            $this->components->error(
                'Configuration key [ai-agent-kit.prompts.file.root_path] must be null or a non-empty string.',
            );

            return null;
        }

        return $this->absolutePath(trim($configuredPath));
    }

    /** Resolve a relative prompt path against the consuming application root. */
    private function absolutePath(string $path): string
    {
        if (
            str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1
        ) {
            return $path;
        }

        return $this->laravel->basePath($path);
    }
}
