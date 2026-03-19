<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Security;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\EncryptionService;
use CreativeCrafts\LaravelAiAgentKit\Security\Exceptions\EncryptionException;
use Illuminate\Contracts\Encryption\Encrypter;
use RuntimeException;
use Throwable;

final readonly class LaravelEncryptionService implements EncryptionService
{
    public function __construct(
        private Encrypter $encrypter,
    ) {
    }

    public function encryptString(string $plaintext): string
    {
        try {
            return $this->encrypter->encrypt($plaintext, false);
        } catch (Throwable $throwable) {
            throw EncryptionException::encryptionFailed($throwable);
        }
    }

    public function decryptString(string $ciphertext): string
    {
        try {
            $plaintext = $this->encrypter->decrypt($ciphertext, false);

            if (!is_string($plaintext)) {
                throw new RuntimeException('Decrypted payload must be a string.');
            }

            return $plaintext;
        } catch (Throwable $throwable) {
            throw EncryptionException::decryptionFailed($throwable);
        }
    }
}
