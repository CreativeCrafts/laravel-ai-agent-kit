<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Memory\Exceptions;

final class ConversationWriteConflictException extends ConversationStoreException
{
    public static function forRevision(
        string $conversationId,
        int $expectedRevision,
        ?int $actualRevision = null,
    ): self {
        $actual = $actualRevision === null ? 'unknown' : (string)$actualRevision;

        return new self(sprintf(
            'Conversation [%s] write conflict: expected revision [%d], actual revision [%s]. Reload before retrying.',
            $conversationId,
            $expectedRevision,
            $actual,
        ));
    }
}
