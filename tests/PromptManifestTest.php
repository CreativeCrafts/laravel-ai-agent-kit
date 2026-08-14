<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions\DuplicatePromptVariableDeclarationException;
use CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions\InvalidPromptManifestException;
use CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions\InvalidPromptVariableNameException;
use CreativeCrafts\LaravelAiAgentKit\Prompts\PromptManifest;

it('models omitted compatibility fields separately from explicit empty variables', function () {
    $legacyManifest = PromptManifest::fromMetadata(
        metadata: [
          'versions' => [
            '1.0.0' => [],
          ],
        ],
        fallbackName: 'legacy.prompt',
        metadataPath: '/prompts/legacy/metadata.php',
    );
    $explicitManifest = PromptManifest::fromMetadata(
        metadata: [
          'name' => 'explicit.prompt',
          'current_version' => '1.0.0',
          'versions' => [
            '1.0.0' => [
              'variables' => [],
            ],
          ],
        ],
        fallbackName: 'ignored.fallback',
        metadataPath: '/prompts/explicit/metadata.php',
    );

    expect($legacyManifest->name)
      ->toBe('legacy.prompt')
      ->and($legacyManifest->currentVersion)->toBeNull()
      ->and($legacyManifest->versions['1.0.0']->templateFile)->toBe('1.0.0.md')
      ->and($legacyManifest->versions['1.0.0']->variables)->toBeNull()
      ->and($explicitManifest->currentVersion)->toBe('1.0.0')
      ->and($explicitManifest->versions['1.0.0']->variables)->toBe([]);
});

it('rejects an unregistered current version', function () {
    PromptManifest::fromMetadata(
        metadata: [
          'name' => 'invalid.current',
          'current_version' => '2.0.0',
          'versions' => [
            '1.0.0' => [],
          ],
        ],
        fallbackName: 'invalid.current',
        metadataPath: '/prompts/invalid-current/metadata.php',
    );
})->throws(InvalidPromptManifestException::class, 'current_version [2.0.0] is not registered');

it('rejects duplicate explicit variable declarations', function () {
    PromptManifest::fromMetadata(
        metadata: [
          'name' => 'duplicate.variables',
          'versions' => [
            '1.0.0' => [
              'variables' => ['name', 'name'],
            ],
          ],
        ],
        fallbackName: 'duplicate.variables',
        metadataPath: '/prompts/duplicates/metadata.php',
    );
})->throws(DuplicatePromptVariableDeclarationException::class, 'name');

it('rejects unsupported explicit variable names', function () {
    PromptManifest::fromMetadata(
        metadata: [
          'name' => 'invalid.variables',
          'versions' => [
            '1.0.0' => [
              'variables' => ['invalid-name'],
            ],
          ],
        ],
        fallbackName: 'invalid.variables',
        metadataPath: '/prompts/invalid-variables/metadata.php',
    );
})->throws(InvalidPromptVariableNameException::class, '[A-Za-z_][A-Za-z0-9_]*');

it('rejects unsafe template paths', function (string $templatePath) {
    PromptManifest::fromMetadata(
        metadata: [
          'name' => 'unsafe.template',
          'versions' => [
            '1.0.0' => [
              'template' => $templatePath,
            ],
          ],
        ],
        fallbackName: 'unsafe.template',
        metadataPath: '/prompts/unsafe-template/metadata.php',
    );
})->throws(InvalidPromptManifestException::class, 'safe relative path')->with([
  'parent traversal' => '../outside.md',
  'nested traversal' => 'nested/../../outside.md',
  'absolute path' => '/outside.md',
  'windows absolute path' => 'C:\\outside.md',
  'control character' => "nested/invalid\n.md",
]);

it('rejects an unsafe implicit template path derived from the version', function () {
    PromptManifest::fromMetadata(
        metadata: [
          'name' => 'unsafe.implicit.template',
          'versions' => [
            '../outside' => [],
          ],
        ],
        fallbackName: 'unsafe.implicit.template',
        metadataPath: '/prompts/unsafe-implicit-template/metadata.php',
    );
})->throws(InvalidPromptManifestException::class, 'safe relative path');
