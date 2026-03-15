<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Memory;

use DateTimeImmutable;

interface ConversationRetentionPurger
{
    public function purgeExpired(?DateTimeImmutable $now = null): int;
}
