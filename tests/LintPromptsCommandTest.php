<?php

declare(strict_types=1);

use Illuminate\Console\Command as ConsoleCommand;

it('lints a valid prompt root', function () {
    $this->artisan('ai:prompts:lint', [
      '--path' => __DIR__ . '/Fixtures/prompts',
    ])
      ->expectsOutputToContain('Prompt manifests are valid')
      ->assertSuccessful();
});

it('reports manifest contract violations', function () {
    $this->artisan('ai:prompts:lint', [
      '--path' => __DIR__ . '/Fixtures/prompts-explicit-variables',
    ])
      ->expectsOutputToContain('uses undeclared variables: actual')
      ->assertExitCode(ConsoleCommand::FAILURE);
});
