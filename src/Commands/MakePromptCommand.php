<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Commands;

use CreativeCrafts\LaravelAiAgentKit\Prompts\PromptManifest;
use CreativeCrafts\LaravelAiAgentKit\Scaffolding\PromptManifestWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;

final class MakePromptCommand extends Command
{
    protected $signature = 'ai:make:prompt
        {name : The prompt name, optionally using dotted or directory notation}
        {--prompt-version=1.0.0 : The prompt version to scaffold}
        {--activate : Make the target version the manifest current_version}
        {--force : Overwrite the destination files if they already exist}';

    protected $description = 'Generate a versioned AI prompt scaffold with template and metadata files.';

    public function __construct(private PromptManifestWriter $manifestWriter)
    {
        parent::__construct();
    }

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

        if (!$this->isSafeVersion($version)) {
            $this->components->error(
                'The prompt version must be a safe single filename segment without separators, traversal markers, or control characters.',
            );

            return self::FAILURE;
        }

        $metadataPath = $this->metadataPath($normalizedName);
        $templatePath = $this->templatePath($normalizedName, $version);
        $promptName = $this->promptName($normalizedName);
        $variableNames = ['audience', 'goal', 'topic'];
        $metadataExists = is_file($metadataPath);

        $directory = dirname($metadataPath);
        $this->ensureDirectoryExists($directory);

        try {
            $metadata = $metadataExists
              ? $this->manifestWriter->read($metadataPath)
              : $this->newManifest($promptName, $version);
            $manifest = PromptManifest::fromMetadata($metadata, $promptName, $metadataPath);
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($manifest->name !== $promptName) {
            $this->components->error(
                "The existing manifest belongs to prompt [{$manifest->name}], not [{$promptName}].",
            );

            return self::FAILURE;
        }

        $targetVersionExists = isset($manifest->versions[$version]);

        if ((($metadataExists && $targetVersionExists) || is_file($templatePath)) && !$this->option('force')) {
            $this->components->error(
                sprintf(
                    'Prompt version [%s] already exists. Use --force to replace only that version.',
                    $version,
                ),
            );

            return self::FAILURE;
        }

        $metadata = $this->updatedManifest(
            metadata: $metadata,
            manifest: $manifest,
            version: $version,
            variableNames: $variableNames,
            activate: (bool)$this->option('activate'),
            metadataExists: $metadataExists,
        );

        $previousTemplate = is_file($templatePath) ? file_get_contents($templatePath) : null;

        if ($previousTemplate === false) {
            $this->components->error("Unable to read existing prompt template [{$templatePath}].");

            return self::FAILURE;
        }

        try {
            $this->writeFileAtomically(
                $templatePath,
                $this->buildTemplateContents(),
                'prompt template scaffold',
            );
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        try {
            $this->manifestWriter->write($metadataPath, $metadata);
        } catch (RuntimeException $exception) {
            $this->restoreTemplate($templatePath, $previousTemplate);
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(
            sprintf(
                'Prompt manifest updated: %s',
                $this->relativeProjectPath($metadataPath),
            ),
        );

        $this->components->info(
            sprintf(
                'Prompt version scaffold created: %s',
                $this->relativeProjectPath($templatePath),
            ),
        );

        return self::SUCCESS;
    }

    private function isSafeVersion(string $version): bool
    {
        if ($version === '' || str_contains($version, '..')) {
            return false;
        }

        return preg_match('/[\\\\\/\x00-\x1F\x7F]/', $version) !== 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function newManifest(string $promptName, string $version): array
    {
        return [
          'name' => $promptName,
          'current_version' => $version,
          'versions' => [
            $version => $this->versionDefinition($version, ['audience', 'goal', 'topic']),
          ],
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     * @param list<string> $variableNames
     * @return array<string, mixed>
     */
    private function updatedManifest(
        array $metadata,
        PromptManifest $manifest,
        string $version,
        array $variableNames,
        bool $activate,
        bool $metadataExists,
    ): array {
        $versions = $metadata['versions'];

        if (!is_array($versions)) {
            throw new RuntimeException('Prompt manifest [versions] must be an array.');
        }

        $versions[$version] = $this->versionDefinition($version, $variableNames);
        $metadata['versions'] = $versions;

        if ($activate || !$metadataExists) {
            $metadata['current_version'] = $version;

            return $metadata;
        }

        if (!array_key_exists('current_version', $metadata)) {
            $metadata['current_version'] = $this->resolveHighestVersion(array_keys($manifest->versions));
        }

        return $metadata;
    }

    /**
     * @param list<string> $variableNames
     * @return array{template: string, variables: list<string>, description: string}
     */
    private function versionDefinition(string $version, array $variableNames): array
    {
        return [
          'template' => $version . '.md',
          'variables' => $variableNames,
          'description' => 'Prompt scaffold generated by ai:make:prompt.',
        ];
    }

    /** @param list<string> $versions */
    private function resolveHighestVersion(array $versions): string
    {
        usort($versions, static fn (string $left, string $right): int => version_compare($right, $left));

        return $versions[0];
    }

    private function restoreTemplate(string $templatePath, string|null $previousTemplate): void
    {
        if ($previousTemplate === null) {
            if (is_file($templatePath)) {
                unlink($templatePath);
            }

            return;
        }

        $this->writeFileAtomically(
            $templatePath,
            $previousTemplate,
            'previous prompt template',
        );
    }

    private function writeFileAtomically(string $path, string $contents, string $label): void
    {
        $temporaryPath = tempnam(dirname($path), '.prompt-template-');

        if (!is_string($temporaryPath)) {
            throw new RuntimeException(
                sprintf(
                    'Failed to create temporary %s beside [%s].',
                    $label,
                    $this->relativeProjectPath($path),
                ),
            );
        }

        try {
            if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
                throw new RuntimeException(
                    sprintf('Failed to write temporary %s.', $label),
                );
            }

            if (!rename($temporaryPath, $path)) {
                throw new RuntimeException(
                    sprintf(
                        'Failed to replace %s at [%s].',
                        $label,
                        $this->relativeProjectPath($path),
                    ),
                );
            }
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
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
