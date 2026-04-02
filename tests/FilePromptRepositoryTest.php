<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Prompts\PromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions\MissingPromptVariableException;
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
