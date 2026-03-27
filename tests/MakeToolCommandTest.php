<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Scaffolding\ProjectInspector;
use Illuminate\Console\Command as ConsoleCommand;
use CreativeCrafts\LaravelAiAgentKit\Scaffolding\ProjectContext;

it('generates a tool scaffold with the expected normalized path namespace and stub content', function () {
    $context = makeToolCommandTestContext();
    $generatedPath = makeToolCommandTestPath('Admin\\SendAlert');

    makeToolCommandTestCleanup($generatedPath);

    $this->artisan('ai:make:tool', [
      'name' => 'admin/send-alert',
    ])->assertSuccessful();

    expect(is_file($generatedPath))->toBeTrue();

    $contents = file_get_contents($generatedPath);
    $toolsNamespace = $context->toolsNamespace();

    expect($contents)->not
      ->toBeFalse()
      ->and($toolsNamespace)->not->toBeNull()
      ->and($contents)->toContain(sprintf('namespace %s\\Admin;', $toolsNamespace))
      ->and($contents)->toContain('final class SendAlert implements Tool')
      ->and($contents)->toContain("return 'send.alert';")
      ->and($contents)->toContain("'type' => 'object'")
      ->and($contents)->toContain("'additionalProperties' => false,");

    makeToolCommandTestCleanup($generatedPath);
});

it('does not overwrite an existing generated file without the force option', function () {
    $generatedPath = makeToolCommandTestPath('ExistingTool');

    makeToolCommandTestCleanup($generatedPath);
    makeToolCommandTestEnsureDirectoryExists(dirname($generatedPath));

    file_put_contents($generatedPath, 'original-content');

    $this->artisan('ai:make:tool', [
      'name' => 'ExistingTool',
    ])->assertExitCode(ConsoleCommand::FAILURE);

    $contents = file_get_contents($generatedPath);

    expect($contents)->toBe('original-content');

    makeToolCommandTestCleanup($generatedPath);
});

it('overwrites an existing generated file when the force option is supplied', function () {
    $context = makeToolCommandTestContext();
    $generatedPath = makeToolCommandTestPath('ForcedTool');

    makeToolCommandTestCleanup($generatedPath);
    makeToolCommandTestEnsureDirectoryExists(dirname($generatedPath));

    file_put_contents($generatedPath, 'stale-content');

    $this->artisan('ai:make:tool', [
      'name' => 'ForcedTool',
      '--force' => true,
    ])->assertSuccessful();

    $contents = file_get_contents($generatedPath);
    $toolsNamespace = $context->toolsNamespace();

    expect($contents)->not
      ->toBeFalse()
      ->and($contents)->not
      ->toBe('stale-content')
      ->and($toolsNamespace)->not->toBeNull()
      ->and($contents)->toContain(sprintf('namespace %s;', $toolsNamespace))
      ->and($contents)->toContain('final class ForcedTool implements Tool')
      ->and($contents)->toContain("return 'forced.tool';");

    makeToolCommandTestCleanup($generatedPath);
});

/**
 * @param non-empty-string $relativeClass
 */
function makeToolCommandTestPath(string $relativeClass): string
{
    return makeToolCommandTestContext()->toolsDirectory() . '/' . str_replace('\\', '/', $relativeClass) . '.php';
}

function makeToolCommandTestEnsureDirectoryExists(string $directory): void
{
    if (is_dir($directory)) {
        return;
    }

    mkdir($directory, 0755, true);
}

function makeToolCommandTestCleanup(string $path): void
{
    if (is_file($path)) {
        unlink($path);
    }

    $directory = dirname($path);
    $root = makeToolCommandTestContext()->toolsDirectory();

    while ($directory !== $root && str_starts_with($directory, $root)) {
        if (!is_dir($directory)) {
            $directory = dirname($directory);

            continue;
        }

        $entries = scandir($directory);

        if ($entries === false || $entries === ['.', '..']) {
            rmdir($directory);
            $directory = dirname($directory);

            continue;
        }

        break;
    }
}

function makeToolCommandTestContext(): ProjectContext
{
    return (new ProjectInspector(base_path()))->inspect();
}
