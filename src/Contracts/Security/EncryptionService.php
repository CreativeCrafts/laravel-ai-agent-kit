<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Security;

interface EncryptionService
{
    public function encryptString(string $plaintext): string;

    public function decryptString(string $ciphertext): string;
}
