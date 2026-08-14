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

it('adds a prompt version without discarding history or activating it', function () {
    makePromptCommandTestCleanup('VersionedPrompt');

    $this->artisan('ai:make:prompt', [
      'name' => 'VersionedPrompt',
      '--prompt-version' => '1.0.0',
    ])->assertSuccessful();

    $this->artisan('ai:make:prompt', [
      'name' => 'VersionedPrompt',
      '--prompt-version' => '2.0.0',
    ])->assertSuccessful();

    $metadata = makePromptCommandTestLoadMetadata('VersionedPrompt');

    expect(is_file(makePromptCommandTestTemplatePath('VersionedPrompt', '1.0.0')))
      ->toBeTrue()
      ->and(is_file(makePromptCommandTestTemplatePath('VersionedPrompt', '2.0.0')))->toBeTrue()
      ->and(array_keys($metadata['versions']))->toBe(['1.0.0', '2.0.0'])
      ->and($metadata['current_version'])->toBe('1.0.0');

    makePromptCommandTestCleanup('VersionedPrompt');
});

it('pins the previous effective version when adding to a legacy manifest', function () {
    makePromptCommandTestCleanup('LegacyPrompt');
    $templatePath = makePromptCommandTestTemplatePath('LegacyPrompt', '1.0.0');
    makePromptCommandTestEnsureDirectoryExists(dirname($templatePath));
    file_put_contents($templatePath, 'Legacy {{audience}} {{goal}} {{topic}}');
    file_put_contents(
        makePromptCommandTestMetadataPath('LegacyPrompt'),
        <<<'PHP'
            <?php

            return [
                'name' => 'legacy.prompt',
                'versions' => [
                    '1.0.0' => [
                        'template' => '1.0.0.md',
                        'variables' => ['audience', 'goal', 'topic'],
                    ],
                ],
            ];
            PHP,
    );

    $this->artisan('ai:make:prompt', [
      'name' => 'LegacyPrompt',
      '--prompt-version' => '2.0.0',
    ])->assertSuccessful();

    expect(makePromptCommandTestLoadMetadata('LegacyPrompt')['current_version'])
      ->toBe('1.0.0');

    makePromptCommandTestCleanup('LegacyPrompt');
});

it('serializes safe non-semver version strings structurally', function () {
    makePromptCommandTestCleanup('QuotedVersionPrompt');
    $version = "release'candidate";

    $this->artisan('ai:make:prompt', [
      'name' => 'QuotedVersionPrompt',
      '--prompt-version' => $version,
    ])->assertSuccessful();

    $metadata = makePromptCommandTestLoadMetadata('QuotedVersionPrompt');

    expect(array_key_exists($version, $metadata['versions']))
      ->toBeTrue()
      ->and($metadata['current_version'])->toBe($version)
      ->and(is_file(makePromptCommandTestTemplatePath('QuotedVersionPrompt', $version)))->toBeTrue();

    makePromptCommandTestCleanup('QuotedVersionPrompt');
});

it('activates a newly added prompt version only when requested', function () {
    makePromptCommandTestCleanup('ActivatedPrompt');

    $this->artisan('ai:make:prompt', [
      'name' => 'ActivatedPrompt',
      '--prompt-version' => '1.0.0',
    ])->assertSuccessful();

    $this->artisan('ai:make:prompt', [
      'name' => 'ActivatedPrompt',
      '--prompt-version' => '2.0.0',
      '--activate' => true,
    ])->assertSuccessful();

    expect(makePromptCommandTestLoadMetadata('ActivatedPrompt')['current_version'])
      ->toBe('2.0.0');

    makePromptCommandTestCleanup('ActivatedPrompt');
});

it('force replaces only the target prompt version', function () {
    makePromptCommandTestCleanup('ForcedPrompt');

    $this->artisan('ai:make:prompt', [
      'name' => 'ForcedPrompt',
      '--prompt-version' => '1.0.0',
    ])->assertSuccessful();
    $this->artisan('ai:make:prompt', [
      'name' => 'ForcedPrompt',
      '--prompt-version' => '2.0.0',
      '--activate' => true,
    ])->assertSuccessful();

    $firstTemplatePath = makePromptCommandTestTemplatePath('ForcedPrompt', '1.0.0');
    $secondTemplatePath = makePromptCommandTestTemplatePath('ForcedPrompt', '2.0.0');
    file_put_contents($firstTemplatePath, 'preserve-version-one');
    file_put_contents($secondTemplatePath, 'replace-version-two');

    $this->artisan('ai:make:prompt', [
      'name' => 'ForcedPrompt',
      '--prompt-version' => '2.0.0',
      '--force' => true,
    ])->assertSuccessful();

    $metadata = makePromptCommandTestLoadMetadata('ForcedPrompt');

    expect(file_get_contents($firstTemplatePath))
      ->toBe('preserve-version-one')
      ->and(file_get_contents($secondTemplatePath))->toContain('{{audience}}')
      ->and(array_keys($metadata['versions']))->toBe(['1.0.0', '2.0.0'])
      ->and($metadata['current_version'])->toBe('2.0.0');

    makePromptCommandTestCleanup('ForcedPrompt');
});

it('rejects unsafe prompt version filename segments', function (string $version) {
    makePromptCommandTestCleanup('UnsafeVersionPrompt');

    $this->artisan('ai:make:prompt', [
      'name' => 'UnsafeVersionPrompt',
      '--prompt-version' => $version,
    ])
      ->expectsOutputToContain('safe single filename segment')
      ->assertExitCode(ConsoleCommand::FAILURE);

    expect(is_file(makePromptCommandTestMetadataPath('UnsafeVersionPrompt')))->toBeFalse();

    makePromptCommandTestCleanup('UnsafeVersionPrompt');
})->with([
  'forward slash' => '2/0',
  'backslash' => '2\\0',
  'parent traversal marker' => '..',
  'control character' => "2\n0",
]);

function makePromptCommandTestTemplatePath(string $relativePath, string $version): string
{
    return base_path('resources/prompts/' . str_replace('\\', '/', $relativePath) . '/' . $version . '.md');
}

function makePromptCommandTestMetadataPath(string $relativePath): string
{
    return base_path('resources/prompts/' . str_replace('\\', '/', $relativePath) . '/metadata.php');
}

/** @return array<string, mixed> */
function makePromptCommandTestLoadMetadata(string $relativePath): array
{
    $metadata = include makePromptCommandTestMetadataPath($relativePath);

    expect($metadata)->toBeArray();

    return $metadata;
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
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($entries as $entry) {
            if (!$entry instanceof SplFileInfo) {
                continue;
            }

            if ($entry->isDir()) {
                rmdir($entry->getPathname());

                continue;
            }

            unlink($entry->getPathname());
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
