<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\EncryptionService;
use CreativeCrafts\LaravelAiAgentKit\Security\LaravelEncryptionService;

it('binds the encryption service contract to the laravel-backed implementation', function () {
    expect(app(EncryptionService::class))->toBeInstanceOf(LaravelEncryptionService::class);
});

it('encrypts and decrypts string payloads through the package abstraction', function () {
    /** @var EncryptionService $service */
    $service = app(EncryptionService::class);

    $ciphertext = $service->encryptString('sensitive-value');
    $plaintext = $service->decryptString($ciphertext);

    expect($ciphertext)
      ->not
      ->toBe('sensitive-value')
      ->and($plaintext)->toBe('sensitive-value');
});
