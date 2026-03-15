<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Memory;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationRetentionPurger;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;

final readonly class DatabaseConversationRetentionPurger implements ConversationRetentionPurger
{
    public function __construct(
        private DatabaseManager $database,
        private ?string $connectionName,
        private string $conversationsTable,
    ) {
    }

    public function purgeExpired(?DateTimeImmutable $now = null): int
    {
        $threshold = ($now ?? new DateTimeImmutable())->format('Y-m-d H:i:s');

        return $this
          ->connection()
          ->table($this->conversationsTable)
          ->whereNull('deleted_at')
          ->whereNotNull('retention_until')
          ->where('retention_until', '<=', $threshold)
          ->delete();
    }

    private function connection(): Connection
    {
        return $this->database->connection($this->connectionName);
    }
}
