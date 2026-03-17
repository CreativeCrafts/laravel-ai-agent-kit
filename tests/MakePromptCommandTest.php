<?php

declare(strict_types=1);

use Illuminate\Console\Command as ConsoleCommand;

it('generates a prompt scaffold with the expected normalized paths template and metadata contents', function () {
    $templatePath = makePromptCommandTestTemplatePath('Support/Reply', '2.1.0');
    $metadataPath = makePromptCommandTestMetadataPath('Support/Reply');

    makePromptCommandTestCleanup('Support/Reply');

    $this->artisan('ai:make:prompt', [
      'name' => 'support.reply',
      '--prompt-version' => '2.1.0',
    ])->assertSuccessful();

    expect(is_file($templatePath))
      ->toBeTrue()
      ->and(is_file($metadataPath))->toBeTrue();

    $templateContents = file_get_contents($templatePath);
    $metadataContents = file_get_contents($metadataPath);

    expect($templateContents)->not
      ->toBeFalse()
      ->and($templateContents)->toContain('You are assisting {{audience}} with {{topic}}.')
      ->and($templateContents)->toContain('{{goal}}');

    expect($metadataContents)->not
      ->toBeFalse()
      ->and($metadataContents)->toContain("'name' => 'support.reply',")
      ->and($metadataContents)->toContain("'current_version' => '2.1.0',")
      ->and($metadataContents)->toContain("'template' => '2.1.0.md',")
      ->and($metadataContents)->toContain("'audience',")
      ->and($metadataContents)->toContain("'goal',")
      ->and($metadataContents)->toContain("'topic',");

    makePromptCommandTestCleanup('Support/Reply');
});

it('does not overwrite an existing prompt scaffold without the force option', function () {
    $templatePath = makePromptCommandTestTemplatePath('ExistingPrompt', '1.0.0');
    $metadataPath = makePromptCommandTestMetadataPath('ExistingPrompt');

    makePromptCommandTestCleanup('ExistingPrompt');
    makePromptCommandTestEnsureDirectoryExists(dirname($templatePath));

    file_put_contents($templatePath, 'original-template');
    file_put_contents($metadataPath, 'original-metadata');

    $this->artisan('ai:make:prompt', [
      'name' => 'ExistingPrompt',
    ])->assertExitCode(ConsoleCommand::FAILURE);

    expect(file_get_contents($templatePath))
      ->toBe('original-template')
      ->and(file_get_contents($metadataPath))->toBe('original-metadata');

    makePromptCommandTestCleanup('ExistingPrompt');
});

it('overwrites an existing prompt scaffold when the force option is supplied', function () {
    $templatePath = makePromptCommandTestTemplatePath('ForcedPrompt', '1.0.0');
    $metadataPath = makePromptCommandTestMetadataPath('ForcedPrompt');

    makePromptCommandTestCleanup('ForcedPrompt');
    makePromptCommandTestEnsureDirectoryExists(dirname($templatePath));

    file_put_contents($templatePath, 'stale-template');
    file_put_contents($metadataPath, 'stale-metadata');

    $this->artisan('ai:make:prompt', [
      'name' => 'ForcedPrompt',
      '--force' => true,
    ])->assertSuccessful();

    $templateContents = file_get_contents($templatePath);
    $metadataContents = file_get_contents($metadataPath);

    expect($templateContents)->not
      ->toBeFalse()
      ->and($templateContents)->not
      ->toBe('stale-template')
      ->and($templateContents)->toContain('{{audience}}')
      ->and($templateContents)->toContain('{{goal}}');

    expect($metadataContents)->not
      ->toBeFalse()
      ->and($metadataContents)->not
      ->toBe('stale-metadata')
      ->and($metadataContents)->toContain("'name' => 'forced.prompt',")
      ->and($metadataContents)->toContain("'current_version' => '1.0.0',");

    makePromptCommandTestCleanup('ForcedPrompt');
});

function makePromptCommandTestTemplatePath(string $relativePath, string $version): string
{
    return base_path('resources/prompts/' . str_replace('\\', '/', $relativePath) . '/' . $version . '.md');
}

function makePromptCommandTestMetadataPath(string $relativePath): string
{
    return base_path('resources/prompts/' . str_replace('\\', '/', $relativePath) . '/metadata.php');
}

function makePromptCommandTestEnsureDirectoryExists(string $directory): void
{
    if (is_dir($directory)) {
        return;
    }

    mkdir($directory, 0755, true);
}

function makePromptCommandTestCleanup(string $relativePath): void
{
    $directory = base_path('resources/prompts/' . str_replace('\\', '/', $relativePath));

    if (is_dir($directory)) {
        $files = glob($directory . '/*');

        if (is_array($files)) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    $root = base_path('resources/prompts');

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
