<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessage;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessageRole;
use InvalidArgumentException;
use JsonException;
use Laravel\Ai\Files\File;

final readonly class AttachmentReplayResult
{
    /**
     * @param list<File> $files
     * @param list<array{type: ?string, reason: string}> $exclusions
     */
    public function __construct(
        public array $files,
        public array $exclusions,
    ) {
    }
}

final class RuntimeAttachmentReplayResolver
{
    public static function resolveLastUserTurn(Conversation $conversation, AttachmentReplayPolicy $policy): AttachmentReplayResult
    {
        $lastUser = null;

        foreach (array_reverse($conversation->messages) as $message) {
            if ($message->role === ConversationMessageRole::User) {
                $lastUser = $message;

                break;
            }
        }

        if ($lastUser === null) {
            return new AttachmentReplayResult([], []);
        }

        return self::resolveFromMessage($lastUser, $policy);
    }

    public static function resolve(Conversation $conversation, AttachmentReplayPolicy $policy): AttachmentReplayResult
    {
        return self::resolveLastUserTurn($conversation, $policy);
    }

    public static function resolveFromMessage(ConversationMessage $message, AttachmentReplayPolicy $policy): AttachmentReplayResult
    {
        $files = [];
        $exclusions = [];
        $allowedIndex = 0;

        $raw = $message->metadata['attachments'] ?? null;
        if ($raw === null || $raw === '') {
            return new AttachmentReplayResult([], []);
        }

        $rows = self::decodeAttachmentRows($raw);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $exclusions[] = ['type' => null, 'reason' => 'invalid_payload'];

                continue;
            }

            /** @var array<string, mixed> $assoc */
            $assoc = [];
            foreach ($row as $key => $value) {
                $assoc[(string) $key] = $value;
            }

            $decision = $policy->evaluate($assoc, $message->createdAt, $allowedIndex);
            if (!$decision->allowed) {
                $type = isset($assoc['type']) && is_string($assoc['type']) ? $assoc['type'] : null;
                $exclusions[] = ['type' => $type, 'reason' => (string)$decision->exclusionReason];

                continue;
            }

            try {
                $files[] = PersistedLaravelAiFileSerializer::fromArray($assoc);
                $allowedIndex++;
            } catch (InvalidArgumentException) {
                $type = isset($assoc['type']) && is_string($assoc['type']) ? $assoc['type'] : null;
                $exclusions[] = ['type' => $type, 'reason' => 'hydration_failed'];
            }
        }

        return new AttachmentReplayResult($files, $exclusions);
    }

    /**
     * @return list<mixed>
     */
    private static function decodeAttachmentRows(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values($raw);
        }

        if (!is_string($raw) || $raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        return array_values($decoded);
    }
}
