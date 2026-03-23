<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Security;

interface Redactor
{
    public function redactText(string $value): string;

    /**
     * @param array<string, mixed> $values
     * @return list<string>
     */
    public function redactKeys(array $values): array;
}
