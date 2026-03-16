<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Prompts\PromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions\MissingPromptVariableException;
use CreativeCrafts\LaravelAiAgentKit\Prompts\Exceptions\PromptNotFoundException;
use CreativeCrafts\LaravelAiAgentKit\Prompts\InMemoryPromptRepository;
use CreativeCrafts\LaravelAiAgentKit\Prompts\PromptTemplate;

it('binds the prompt repository contract to the default in-memory implementation', function () {
    $repository = app(PromptRepository::class);

    expect($repository)->toBeInstanceOf(InMemoryPromptRepository::class);
});

it('renders a prompt deterministically with explicit version selection', function () {
    $repository = new InMemoryPromptRepository([
      'support.reply' => [
        '1.0.0' => 'Hello {{name}}, your ticket is {{ticket_id}}.',
        '2.0.0' => 'Hi {{name}} — case {{ticket_id}} is now assigned to {{agent}}.',
      ],
    ]);

    expect($repository->render('support.reply', [
      'name' => 'Prince',
      'ticket_id' => 42,
      'agent' => 'Ada',
    ], '2.0.0'))->toBe('Hi Prince — case 42 is now assigned to Ada.');
});

it('resolves the latest available prompt version when no version is requested', function () {
    $repository = new InMemoryPromptRepository([
      'greeting' => [
        '1.0.0' => 'Hello {{name}}.',
        '2.1.0' => 'Welcome back {{name}}.',
        '2.0.0' => 'Hi {{name}}.',
      ],
    ]);

    $template = $repository->get('greeting');

    expect($template)
      ->toBeInstanceOf(PromptTemplate::class)
      ->and($template->version)->toBe('2.1.0')
      ->and($repository->render('greeting', ['name' => 'Prince']))->toBe('Welcome back Prince.');
});

it('fails deterministically when required variables are missing', function () {
    $repository = new InMemoryPromptRepository([
      'summary' => [
        '1.0.0' => 'User: {{user}} | Topic: {{topic}}',
      ],
    ]);

    $repository->render('summary', ['user' => 'Prince'], '1.0.0');
})->throws(MissingPromptVariableException::class, 'topic');

it('treats null variables as empty strings during interpolation', function () {
    $repository = new InMemoryPromptRepository([
      'nullable' => [
        '1.0.0' => 'Value: {{value}}.',
      ],
    ]);

    expect($repository->render('nullable', ['value' => null], '1.0.0'))
      ->toBe('Value: .');
});

it('reports missing prompts and versions cleanly', function () {
    $repository = new InMemoryPromptRepository([
      'greeting' => [
        '1.0.0' => 'Hello {{name}}.',
      ],
    ]);

    expect($repository->has('greeting', '1.0.0'))
      ->toBeTrue()
      ->and($repository->has('greeting', '9.9.9'))->toBeFalse()
      ->and($repository->has('missing'))->toBeFalse();

    $repository->get('greeting', '9.9.9');
})->throws(PromptNotFoundException::class, 'version [9.9.9]');
