<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Commands;

use CreativeCrafts\LaravelAiAgentKit\Scaffolding\ProjectInspector;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;

final class MakeAgentCommand extends Command
{
    protected $signature = 'ai:make:agent
        {name : The agent class name, optionally relative to the Agents namespace}
        {--force : Overwrite the destination file if it already exists}';

    protected $description = 'Generate a new AI agent scaffold aligned to the inspected project namespace and paths.';

    public function handle(): int
    {
        /** @var string $rawName */
        $rawName = $this->argument('name');
        $normalizedName = $this->normalizeClassName($rawName);

        if ($normalizedName === '') {
            $this->components->error('The agent name must resolve to a non-empty class name.');

            return self::FAILURE;
        }

        $projectContext = (new ProjectInspector($this->laravel->basePath()))->inspect();
        $rootNamespace = $projectContext->rootNamespace;

        if ($rootNamespace === null || $rootNamespace === '') {
            $this->components->error('Unable to determine the PSR-4 root namespace for agent scaffolding.');

            return self::FAILURE;
        }

        $relativeClass = Str::startsWith($normalizedName, 'Agents\\')
          ? $normalizedName
          : 'Agents\\' . $normalizedName;

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

        $written = file_put_contents($destinationPath, $this->buildClassContents($fullyQualifiedClass, $relativeClass));

        if ($written === false) {
            throw new RuntimeException(
                sprintf(
                    'Failed to write agent scaffold to [%s].',
                    $this->relativeProjectPath($destinationPath),
                ),
            );
        }

        $this->components->info(
            sprintf(
                'Agent scaffold created: %s',
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

    private function buildClassContents(string $fullyQualifiedClass, string $relativeClass): string
    {
        $namespace = Str::beforeLast($fullyQualifiedClass, '\\');
        $className = Str::afterLast($fullyQualifiedClass, '\\');
        $promptName = $this->promptNameFromRelativeClass($relativeClass, $className);
        $agentKey = $promptName === '' ? Str::of($className)->snake('.')->toString() : $promptName;
        $displayName = Str::headline(Str::of($className)->replace('Agent', '')->toString());
        $defaultProviderProfile = config('ai-agent-kit.default_provider', 'null');

        if (!is_string($defaultProviderProfile) || $defaultProviderProfile === '') {
            $defaultProviderProfile = 'null';
        }

        $stub = <<<'PHP'
            <?php
            
            declare(strict_types=1);
            
            namespace {{ namespace }};
            
            use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\Agent;
            use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
            use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionContext;
            use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentExecutionResult;
            use CreativeCrafts\LaravelAiAgentKit\Blueprints\PromptBlueprint;
            use CreativeCrafts\LaravelAiAgentKit\LaravelAiAgentKit;
            
            /**
             * Example agent generated by the Laravel AI Agent Kit.
             */
            final class {{ class }} implements Agent
            {
                public function definition(): AgentDefinition
                {
                    return new AgentDefinition(
                        key: '{{ agentKey }}',
                        displayName: '{{ displayName }}',
                        requiredCapabilities: [],
                        primaryProviderProfile: '{{ primaryProviderProfile }}',
                    );
                }
            
                /**
                 * @param array<string, scalar|null> $variables
                 */
                public function blueprint(array $variables = []): PromptBlueprint
                {
                    return LaravelAiAgentKit::prompt('{{ prompt }}')
                        ->withVariables($variables)
                        ->withInstructions([
                            'Describe the agent objective, constraints, and output expectations here.',
                        ]);
                }
            
                public function handle(AgentExecutionContext $context): AgentExecutionResult
                {
                    return new AgentExecutionResult(
                        kind: AgentExecutionResult::KIND_COMPLETE,
                        output: [
                            'message' => 'Replace this stub with your agent logic.',
                            'task' => $context->task,
                        ],
                        summary: 'Example agent completed.',
                    );
                }
            }
            PHP;

        return str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ prompt }}', '{{ agentKey }}', '{{ displayName }}', '{{ primaryProviderProfile }}'],
            [$namespace, $className, $promptName, $agentKey, $displayName, $defaultProviderProfile],
            $stub,
        );
    }

    private function promptNameFromRelativeClass(string $relativeClass, string $className): string
    {
        $withoutPrefix = Str::startsWith($relativeClass, 'Agents\\')
          ? Str::after($relativeClass, 'Agents\\')
          : $relativeClass;

        $segments = explode('\\', $withoutPrefix);
        $nameSegments = [];

        foreach ($segments as $segment) {
            $normalized = $segment;

            if ($segment === $className && Str::endsWith($segment, 'Agent')) {
                $stripped = Str::beforeLast($segment, 'Agent');

                if ($stripped !== '') {
                    $normalized = $stripped;
                }
            }

            $nameSegments[] = Str::of($normalized)
              ->snake()
              ->replace('_', '.')
              ->toString();
        }

        return implode('.', array_filter($nameSegments, static fn (string $segment): bool => $segment !== ''));
    }
}
