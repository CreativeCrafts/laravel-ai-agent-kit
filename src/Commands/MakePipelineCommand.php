<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Commands;

use CreativeCrafts\LaravelAiAgentKit\Scaffolding\ProjectInspector;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

final class MakePipelineCommand extends Command
{
    protected $signature = 'ai:make:pipeline
        {name : The pipeline class name, optionally relative to the Pipelines namespace}
        {--force : Overwrite the destination file if it already exists}';

    protected $description = 'Generate a new AI pipeline scaffold aligned to the inspected project namespace and paths.';

    /**
     * @throws JsonException
     */
    public function handle(): int
    {
        /** @var string $rawName */
        $rawName = $this->argument('name');
        $normalizedName = $this->normalizeClassName($rawName);

        if ($normalizedName === '') {
            $this->components->error('The pipeline name must resolve to a non-empty class name.');

            return self::FAILURE;
        }

        $projectContext = (new ProjectInspector($this->laravel->basePath()))->inspect();
        $rootNamespace = $projectContext->rootNamespace;

        if ($rootNamespace === null || $rootNamespace === '') {
            $this->components->error('Unable to determine the PSR-4 root namespace for pipeline scaffolding.');

            return self::FAILURE;
        }

        $relativeClass = Str::startsWith($normalizedName, 'Pipelines\\')
          ? $normalizedName
          : 'Pipelines\\' . $normalizedName;

        $fullyQualifiedClass = rtrim($rootNamespace, '\\') . '\\' . $relativeClass;
        $destinationPath = $this->destinationPath($projectContext->sourceDirectory, $relativeClass);

        if (is_file($destinationPath) && !$this->option('force')) {
            $this->components->error(
                sprintf(
                    'The file [%s] already exists. Use --force to overwrite it.',
                    $this->relativeProjectPath($destinationPath),
                ),
            );

            return self::FAILURE;
        }

        $this->ensureDirectoryExists(dirname($destinationPath));

        $written = file_put_contents($destinationPath, $this->buildClassContents($fullyQualifiedClass));

        if ($written === false) {
            throw new RuntimeException(
                sprintf(
                    'Failed to write pipeline scaffold to [%s].',
                    $this->relativeProjectPath($destinationPath),
                ),
            );
        }

        $this->components->info(
            sprintf(
                'Pipeline scaffold created: %s',
                $this->relativeProjectPath($destinationPath),
            ),
        );

        return self::SUCCESS;
    }

    private function normalizeClassName(string $name): string
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            return '';
        }

        $segments = preg_split('/[\/\\\\]+/', $trimmed);

        if (!is_array($segments)) {
            return '';
        }

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

        return implode('\\', $normalizedSegments);
    }

    private function destinationPath(string $sourceDirectory, string $relativeClass): string
    {
        return rtrim($sourceDirectory, DIRECTORY_SEPARATOR) . '/' . str_replace('\\', '/', $relativeClass) . '.php';
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

    private function buildClassContents(string $fullyQualifiedClass): string
    {
        $namespace = Str::beforeLast($fullyQualifiedClass, '\\');
        $className = Str::afterLast($fullyQualifiedClass, '\\');

        return <<<PHP
            <?php
            
            declare(strict_types=1);
            
            namespace {$namespace};
            
            use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\QueuedPipelineDefinition;
            use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Pipeline;
            use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\PipelineBuilder;
            
            final class {$className} implements QueuedPipelineDefinition
            {
                public function build(): Pipeline
                {
                    return PipelineBuilder::make()
                        // ->addStep(new FirstPipelineStep())
                        ->build();
                }
            }
            PHP;
    }
}
