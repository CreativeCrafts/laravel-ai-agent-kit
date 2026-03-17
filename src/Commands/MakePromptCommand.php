<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;

final class MakePromptCommand extends Command
{
    protected $signature = 'ai:make:prompt
        {name : The prompt name, optionally using dotted or directory notation}
        {--prompt-version=1.0.0 : The prompt version to scaffold}
        {--force : Overwrite the destination files if they already exist}';

    protected $description = 'Generate a versioned AI prompt scaffold with template and metadata files.';

    public function handle(): int
    {
        /** @var string $rawName */
        $rawName = $this->argument('name');
        $normalizedName = $this->normalizePromptName($rawName);

        if ($normalizedName === '') {
            $this->components->error('The prompt name must resolve to a non-empty prompt path.');

            return self::FAILURE;
        }

        /** @var string $rawVersion */
        $rawVersion = $this->option('prompt-version');
        $version = trim($rawVersion);

        if ($version === '') {
            $this->components->error('The prompt version must be a non-empty string.');

            return self::FAILURE;
        }

        $metadataPath = $this->metadataPath($normalizedName);
        $templatePath = $this->templatePath($normalizedName, $version);

        if (!$this->option('force')) {
            $existingPath = $this->firstExistingPath([$metadataPath, $templatePath]);

            if ($existingPath !== null) {
                $this->components->error(
                    sprintf(
                        'The file [%s] already exists. Use --force to overwrite it.',
                        $this->relativeProjectPath($existingPath),
                    ),
                );

                return self::FAILURE;
            }
        }

        $directory = dirname($metadataPath);
        $this->ensureDirectoryExists($directory);

        $promptName = $this->promptName($normalizedName);
        $variableNames = ['audience', 'goal', 'topic'];

        $this->writeFile(
            $metadataPath,
            $this->buildMetadataContents($promptName, $version, $variableNames),
            'prompt metadata scaffold',
        );

        $this->writeFile(
            $templatePath,
            $this->buildTemplateContents(),
            'prompt template scaffold',
        );

        $this->components->info(
            sprintf(
                'Prompt scaffold created: %s',
                $this->relativeProjectPath($metadataPath),
            ),
        );

        $this->components->info(
            sprintf(
                'Prompt scaffold created: %s',
                $this->relativeProjectPath($templatePath),
            ),
        );

        return self::SUCCESS;
    }

    private function normalizePromptName(string $name): string
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            return '';
        }

        $normalizedDelimiters = str_replace(['\\', '.'], '/', $trimmed);
        $segments = explode('/', $normalizedDelimiters);
        $normalizedSegments = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);

            if ($segment === '') {
                continue;
            }

            $studly = Str::studly($segment);
            $sanitized = preg_replace('/[^A-Za-z0-9_]/', '', $studly);
            if (!is_string($sanitized)) {
                continue;
            }
            if ($sanitized === '') {
                continue;
            }

            if (preg_match('/^\d/', $sanitized) === 1) {
                $sanitized = '_' . $sanitized;
            }

            $normalizedSegments[] = $sanitized;
        }

        return implode('/', $normalizedSegments);
    }

    private function metadataPath(string $normalizedName): string
    {
        return $this->laravel->basePath('resources/prompts/' . $normalizedName . '/metadata.php');
    }

    private function templatePath(string $normalizedName, string $version): string
    {
        return $this->laravel->basePath('resources/prompts/' . $normalizedName . '/' . $version . '.md');
    }

    /**
     * @param list<string> $paths
     */
    private function firstExistingPath(array $paths): ?string
    {
        foreach ($paths as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function relativeProjectPath(string $absolutePath): string
    {
        return Str::after($absolutePath, rtrim($this->laravel->basePath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException(
                sprintf(
                    'Failed to create directory [%s].',
                    $this->relativeProjectPath($directory),
                ),
            );
        }
    }

    private function promptName(string $normalizedName): string
    {
        $segments = explode('/', $normalizedName);
        $nameSegments = [];

        foreach ($segments as $segment) {
            $nameSegments[] = Str::of($segment)
              ->snake()
              ->replace('_', '.')
              ->toString();
        }

        return implode('.', $nameSegments);
    }

    private function writeFile(string $path, string $contents, string $label): void
    {
        $written = file_put_contents($path, $contents);

        if ($written !== false) {
            return;
        }

        throw new RuntimeException(
            sprintf(
                'Failed to write %s to [%s].',
                $label,
                $this->relativeProjectPath($path),
            ),
        );
    }

    /**
     * @param list<string> $variableNames
     */
    private function buildMetadataContents(string $promptName, string $version, array $variableNames): string
    {
        $variableList = implode(
            ",\n",
            array_map(
                static fn (string $variable): string => "                '{$variable}',",
                $variableNames,
            ),
        );

        return <<<PHP
            <?php
            
            declare(strict_types=1);
            
            return [
                'name' => '{$promptName}',
                'current_version' => '{$version}',
                'versions' => [
                    '{$version}' => [
                        'template' => '{$version}.md',
                        'variables' => [
            {$variableList}
                        ],
                        'description' => 'Initial prompt scaffold generated by ai:make:prompt.',
                    ],
                ],
            ];
            PHP;
    }

    private function buildTemplateContents(): string
    {
        return <<<'MARKDOWN'
            You are assisting {{audience}} with {{topic}}.
            
            Goal:
            {{goal}}
            
            Respond clearly, accurately, and within the package conventions.
            MARKDOWN;
    }
}
