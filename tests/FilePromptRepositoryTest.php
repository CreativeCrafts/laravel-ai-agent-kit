<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Prompts\PromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions\InvalidPromptManifestException;
use CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions\MissingPromptVariableException;
use CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions\UndeclaredPromptVariableException;
use CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions\UnusedPromptVariableDeclarationException;
use CreativeCrafts\LaravelAiAgentKit\Prompts\FilePromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Prompts\InMemoryPromptRepository;

it('loads prompt templates from filesystem metadata fixtures', function () {
    $repository = new FilePromptRepository(promptFixturesPath());

    expect($repository->has('support.reply'))
      ->toBeTrue()
      ->and(
          trim($repository->render('support.reply', [
          'name' => 'Prince',
          'ticket_id' => 42,
          'agent' => 'Ada',
        ])),
      )
      ->toBe('Hi Prince — case 42 is now assigned to Ada.');
});

it('supports deterministic explicit version rendering from filesystem prompts', function () {
    $repository = new FilePromptRepository(promptFixturesPath());

    expect(trim($repository->render('support.reply', [
      'name' => 'Prince',
      'ticket_id' => 42,
    ], '1.0.0')))->toBe('Hello Prince, your ticket is 42.');
});

it('throws for missing variables from filesystem prompts', function () {
    $repository = new FilePromptRepository(promptFixturesPath());

    $repository->render('support.reply', [
      'name' => 'Prince',
      'ticket_id' => 42,
    ], '2.0.0');
})->throws(MissingPromptVariableException::class, 'agent');

it('preserves legacy metadata that omits current version and variable declarations', function () {
    $repository = new FilePromptRepository(promptLegacyFixturesPath());

    expect($repository->get('legacy.greeting')->version)
      ->toBe('2.0.0')
      ->and($repository->get('legacy.greeting')->variables)->toBe(['name'])
      ->and(trim($repository->render('legacy.greeting', ['name' => 'Prince'])))
      ->toBe('Welcome Prince.');
});

it('uses the manifest current version when it differs from the highest version', function () {
    $repository = new FilePromptRepository(promptCurrentVersionFixturesPath());

    expect($repository->get('versioned.greeting')->version)
      ->toBe('1.0.0')
      ->and(trim($repository->render('versioned.greeting', ['name' => 'Prince'])))
      ->toBe('Hello Prince.');

    expect(trim($repository->render('versioned.greeting', ['name' => 'Prince'], '2.0.0')))
      ->toBe('Welcome to version two, Prince.');
});

it('treats scaffolded variable declarations as authoritative', function () {
    new FilePromptRepository(promptExplicitVariablesFixturesPath());
})->throws(UndeclaredPromptVariableException::class, 'actual');

it('treats an explicit empty variable declaration as authoritative', function () {
    $repository = new FilePromptRepository(promptExplicitEmptyFixturesPath());

    expect($repository->get('literal.example')->variables)
      ->toBe([])
      ->and(trim($repository->render('literal.example')))
      ->toBe('Inspect {{payload}} without interpolation.');
});

it('rejects unused explicit variable declarations', function () {
    new FilePromptRepository(promptUnusedVariablesFixturesPath());
})->throws(UnusedPromptVariableDeclarationException::class, 'unused');

it('rejects missing template files instead of silently skipping the version', function () {
    new FilePromptRepository(promptMissingTemplateFixturesPath());
})->throws(InvalidPromptManifestException::class, 'absent.md');

it('binds the prompt repository to filesystem implementation when configured', function () {
    config()->set('ai-agent-kit.prompts.default_driver', 'file');
    config()->set('ai-agent-kit.prompts.file.root_path', promptFixturesPath());

    app()->forgetInstance(PromptRepository::class);
    app()->forgetInstance(FilePromptRepository::class);
    app()->forgetInstance(InMemoryPromptRepository::class);

    /** @var PromptRepository $repository */
    $repository = app(PromptRepository::class);

    expect($repository)
      ->toBeInstanceOf(FilePromptRepository::class)
      ->and(
          trim($repository->render('support.reply', [
          'name' => 'Prince',
          'ticket_id' => 42,
          'agent' => 'Ada',
        ])),
      )
      ->toBe('Hi Prince — case 42 is now assigned to Ada.');
});

function promptFixturesPath(): string
{
    return __DIR__ . '/Fixtures/prompts';
}

function promptLegacyFixturesPath(): string
{
    return __DIR__ . '/Fixtures/prompts-legacy';
}

function promptCurrentVersionFixturesPath(): string
{
    return __DIR__ . '/Fixtures/prompts-current-version';
}

function promptExplicitVariablesFixturesPath(): string
{
    return __DIR__ . '/Fixtures/prompts-explicit-variables';
}

function promptExplicitEmptyFixturesPath(): string
{
    return __DIR__ . '/Fixtures/prompts-explicit-empty';
}

function promptUnusedVariablesFixturesPath(): string
{
    return __DIR__ . '/Fixtures/prompts-unused-variables';
}

function promptMissingTemplateFixturesPath(): string
{
    return __DIR__ . '/Fixtures/prompts-missing-template';
}
