<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Scaffolding\ProjectInspector;

it('detects the current package repository context safely', function () {
    $context = (new ProjectInspector(projectInspectorPackageRoot()))->inspect();

    expect($context->projectType)
      ->toBe('laravel_package')
      ->and($context->hasComposerJson)->toBeTrue()
      ->and($context->rootNamespace)->toBe('CreativeCrafts\\LaravelAiAgentKit\\')
      ->and($context->hasLaravelAiSdk)->toBeTrue()
      ->and($context->sourceDirectory)->toBe(projectInspectorPackageRoot('src'))
      ->and($context->promptsDirectory)->toBe(projectInspectorPackageRoot('resources/prompts'))
      ->and($context->toolsDirectory())->toBe(projectInspectorPackageRoot('src/Tools'))
      ->and($context->agentsDirectory())->toBe(projectInspectorPackageRoot('src/Agents'))
      ->and($context->pipelinesDirectory())->toBe(projectInspectorPackageRoot('src/Pipelines'))
      ->and($context->toolsNamespace())->toBe('CreativeCrafts\\LaravelAiAgentKit\\Tools')
      ->and($context->agentsNamespace())->toBe('CreativeCrafts\\LaravelAiAgentKit\\Agents')
      ->and($context->pipelinesNamespace())->toBe('CreativeCrafts\\LaravelAiAgentKit\\Pipelines');
});

it('detects laravel application context from composer metadata and bootstrap structure', function () {
    $projectRoot = projectInspectorTestMakeDirectory('laravel-app');

    projectInspectorTestWriteFile($projectRoot . '/composer.json', json_encode([
      'name' => 'acme/demo-app',
      'type' => 'project',
      'require' => [
        'php' => '^8.3',
        'laravel/framework' => '^12.0',
        'laravel/ai' => '^0.3',
      ],
      'autoload' => [
        'psr-4' => [
          'App\\' => 'app/',
        ],
      ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    projectInspectorTestWriteFile($projectRoot . '/composer.lock', json_encode([
      'packages' => [
        [
          'name' => 'laravel/framework',
          'version' => 'v12.4.1',
        ],
        [
          'name' => 'laravel/ai',
          'version' => 'v0.3.2',
        ],
      ],
      'packages-dev' => [],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    projectInspectorTestWriteFile($projectRoot . '/bootstrap/app.php', "<?php\n");

    $context = (new ProjectInspector($projectRoot))->inspect();

    expect($context->projectType)
      ->toBe('laravel_app')
      ->and($context->hasComposerJson)->toBeTrue()
      ->and($context->hasComposerLock)->toBeTrue()
      ->and($context->rootNamespace)->toBe('App\\')
      ->and($context->laravelVersion)->toBe('12.4.1')
      ->and($context->hasLaravelAiSdk)->toBeTrue()
      ->and($context->sourceDirectory)->toBe($projectRoot . '/app')
      ->and($context->promptsDirectory)->toBe($projectRoot . '/resources/prompts')
      ->and($context->toolsDirectory())->toBe($projectRoot . '/app/Tools')
      ->and($context->agentsDirectory())->toBe($projectRoot . '/app/Agents')
      ->and($context->pipelinesDirectory())->toBe($projectRoot . '/app/Pipelines')
      ->and($context->toolsNamespace())->toBe('App\\Tools')
      ->and($context->agentsNamespace())->toBe('App\\Agents')
      ->and($context->pipelinesNamespace())->toBe('App\\Pipelines');

    projectInspectorTestDeleteDirectory($projectRoot);
});

it('falls back safely when composer metadata is missing', function () {
    $projectRoot = projectInspectorTestMakeDirectory('fallback');

    $context = (new ProjectInspector($projectRoot))->inspect();

    expect($context->projectType)
      ->toBe('unknown')
      ->and($context->hasComposerJson)->toBeFalse()
      ->and($context->hasComposerLock)->toBeFalse()
      ->and($context->rootNamespace)->toBeNull()
      ->and($context->laravelVersion)->toBeNull()
      ->and($context->hasLaravelAiSdk)->toBeFalse()
      ->and($context->sourceDirectory)->toBe($projectRoot . '/src')
      ->and($context->promptsDirectory)->toBe($projectRoot . '/resources/prompts')
      ->and($context->toolsNamespace())->toBeNull()
      ->and($context->agentsNamespace())->toBeNull()
      ->and($context->pipelinesNamespace())->toBeNull();

    projectInspectorTestDeleteDirectory($projectRoot);
});

function projectInspectorPackageRoot(string $path = ''): string
{
    $root = dirname(__DIR__);

    if ($path === '') {
        return $root;
    }

    return $root . '/' . ltrim($path, '/');
}

function projectInspectorTestMakeDirectory(string $suffix): string
{
    $directory = sys_get_temp_dir() . '/laravel-ai-agent-kit-project-inspector-' . $suffix . '-' . uniqid('', true);

    mkdir($directory, 0755, true);

    return $directory;
}

function projectInspectorTestWriteFile(string $path, string|false $contents): void
{
    $directory = dirname($path);

    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    if (!is_string($contents)) {
        throw new RuntimeException(sprintf('Failed to encode test fixture for [%s].', $path));
    }

    file_put_contents($path, $contents);
}

function projectInspectorTestDeleteDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $entries = scandir($directory);

    if ($entries === false) {
        return;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $directory . DIRECTORY_SEPARATOR . $entry;

        if (is_dir($path)) {
            projectInspectorTestDeleteDirectory($path);

            continue;
        }

        unlink($path);
    }

    rmdir($directory);
}
