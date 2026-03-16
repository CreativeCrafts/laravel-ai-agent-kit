<?php

declare(strict_types=1);

use Illuminate\Console\Command as ConsoleCommand;

it('generates a tool scaffold with the expected normalized path namespace and stub content', function () {
    $generatedPath = makeToolCommandTestPath('Admin\\SendAlert');

    makeToolCommandTestCleanup($generatedPath);

    $this->artisan('ai:make:tool', [
      'name' => 'admin/send-alert',
    ])->assertSuccessful();

    expect(is_file($generatedPath))->toBeTrue();

    $contents = file_get_contents($generatedPath);

    expect($contents)->not
      ->toBeFalse()
      ->and($contents)->toContain('namespace CreativeCrafts\\LaravelAiAgentKit\\Tools\\Admin;')
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
    $generatedPath = makeToolCommandTestPath('ForcedTool');

    makeToolCommandTestCleanup($generatedPath);
    makeToolCommandTestEnsureDirectoryExists(dirname($generatedPath));

    file_put_contents($generatedPath, 'stale-content');

    $this->artisan('ai:make:tool', [
      'name' => 'ForcedTool',
      '--force' => true,
    ])->assertSuccessful();

    $contents = file_get_contents($generatedPath);

    expect($contents)->not
      ->toBeFalse()
      ->and($contents)->not
      ->toBe('stale-content')
      ->and($contents)->toContain('namespace CreativeCrafts\\LaravelAiAgentKit\\Tools;')
      ->and($contents)->toContain('final class ForcedTool implements Tool')
      ->and($contents)->toContain("return 'forced.tool';");

    makeToolCommandTestCleanup($generatedPath);
});

/**
 * @param non-empty-string $relativeClass
 */
function makeToolCommandTestPath(string $relativeClass): string
{
    return base_path('src/Tools/' . str_replace('\\', '/', $relativeClass) . '.php');
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
    $root = base_path('src/Tools');

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
