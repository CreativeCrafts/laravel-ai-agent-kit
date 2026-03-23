<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\Redactor;
use CreativeCrafts\LaravelAiAgentKit\Security\DefaultRedactor;

it('binds the redactor contract to the default redactor implementation', function () {
    expect(app(Redactor::class))->toBeInstanceOf(DefaultRedactor::class);
});

it('redacts raw text into a deterministic length-only marker', function () {
    $redactor = new DefaultRedactor();

    expect($redactor->redactText('sensitive-user-content'))->toBe('[redacted:22]');
});

it('redacts sensitive keys while preserving safe keys and stable ordering', function () {
    $redactor = new DefaultRedactor();

    expect($redactor->redactKeys([
      'secret' => 'value',
      'safe_key' => 'visible-only-by-key',
      'token' => 'another-secret',
      'email_address' => 'prince@example.com',
      'trace_id' => 'trace-001',
    ]))->toBe([
      '[redacted-key]',
      'safe_key',
      'trace_id',
    ]);
});
