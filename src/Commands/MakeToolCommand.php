<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;

final class MakeToolCommand extends Command
{
    protected $signature = 'ai:make:tool
        {name : The tool class name, optionally relative to the Tools namespace}
        {--force : Overwrite the destination file if it already exists}';

    protected $description = 'Generate a new AI tool scaffold with schema and handler methods.';

    public function handle(): int
    {
        $rawName = (string)$this->argument('name');
        $normalizedName = $this->normalizeClassName($rawName);

        if ($normalizedName === '') {
            $this->components->error('The tool name must resolve to a non-empty class name.');

            return self::FAILURE;
        }

        $relativeClass = Str::startsWith($normalizedName, 'Tools\\')
          ? $normalizedName
          : 'Tools\\' . $normalizedName;

        $fullyQualifiedClass = 'CreativeCrafts\\LaravelAiAgentKit\\' . $relativeClass;
        $destinationPath = $this->destinationPath($relativeClass);

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
                    'Failed to write tool scaffold to [%s].',
                    $this->relativeProjectPath($destinationPath),
                ),
            );
        }

        $this->components->info(
            sprintf(
                'Tool scaffold created: %s',
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

    private function destinationPath(string $relativeClass): string
    {
        $projectRoot = $this->laravel->basePath();

        return $projectRoot . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';
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
        $toolName = Str::of($className)
          ->snake('.')
          ->replace('_', '.')
          ->toString();

        return <<<PHP
            <?php
            
            declare(strict_types=1);
            
            namespace {$namespace};
            
            use CreativeCrafts\LaravelAiAgentKit\Contracts\Tools\Tool;
            
            final class {$className} implements Tool
            {
                public function name(): string
                {
                    return '{$toolName}';
                }
            
                /**
                 * @return array<string, mixed>
                 */
                public function inputSchema(): array
                {
                    return [
                        'type' => 'object',
                        'properties' => [
                            'input' => ['type' => 'string'],
                        ],
                        'required' => ['input'],
                        'additionalProperties' => false,
                    ];
                }
            
                /**
                 * @param array<string, mixed> \$input
                 *
                 * @return array<string, mixed>
                 */
                public function execute(array \$input): array
                {
                    return [
                        'ok' => true,
                        'received' => \$input,
                    ];
                }
            }
            PHP;
    }
}
