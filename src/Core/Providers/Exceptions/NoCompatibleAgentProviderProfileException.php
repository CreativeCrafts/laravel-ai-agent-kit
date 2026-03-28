<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Providers\Exceptions;

use RuntimeException;

final class NoCompatibleAgentProviderProfileException extends RuntimeException
{
    /**
     * @param list<string> $attempts
     */
    private function __construct(string $message, private readonly array $attempts)
    {
        parent::__construct($message);
    }

    /**
     * @param list<string> $attempts
     */
    public static function forAgent(string $agentKey, array $attempts): self
    {
        return new self(
            sprintf(
                'Agent [%s] does not have a compatible configured provider profile.',
                $agentKey,
            ),
            $attempts,
        );
    }

    /**
     * @return list<string>
     */
    public function attempts(): array
    {
        return $this->attempts;
    }
}
