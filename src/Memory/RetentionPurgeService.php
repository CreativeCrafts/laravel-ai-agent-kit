<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Memory;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationRetentionPurger;
use CreativeCrafts\LaravelAiAgentKit\Memory\Exceptions\ConversationRetentionPurgeException;
use DateTimeImmutable;
use Throwable;

final readonly class RetentionPurgeService
{
    public function __construct(
        private ConversationRetentionPurger $purger,
        private string $memoryDriver,
    ) {
    }

    public function purge(?DateTimeImmutable $now = null): int
    {
        try {
            return $this->purger->purgeExpired($now);
        } catch (Throwable $throwable) {
            throw ConversationRetentionPurgeException::forDriver($this->memoryDriver, $throwable);
        }
    }
}
