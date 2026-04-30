<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Memory;

use DateMalformedStringException;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use JsonException;
use Laravel\Ai\Storage\DatabaseConversationStore;
use DateTimeInterface;

/**
 * Reads Laravel AI default conversation tables into package {@see Conversation} / {@see ConversationMessage}.
 *
 * Source schema matches {@see DatabaseConversationStore}: `agent_conversations` rows
 * (`id`, `user_id`, `title`, timestamps) and `agent_conversation_messages` (`id`, `conversation_id`, `user_id`,
 * `agent`, `role`, `content`, JSON `attachments`, `tool_calls`, `tool_results`, `usage`, `meta`).
 *
 * Mapping to package DTOs:
 * - Conversation `id` uses the legacy UUID string (same as Laravel AI conversation id).
 * - Conversation `metadata['laravel_ai']` holds `title` and `user_id` (SDK-level context, not encrypted in legacy).
 * - Each row becomes one {@see ConversationMessage}; `role` maps `user`/`assistant` (other roles become user).
 * - Message `id` is the legacy message row `id` (UUID).
 * - Message `metadata['laravel_ai']` carries `message_row_id`, `agent`, and for user rows optional `attachments`;
 *   for assistant rows optional `tool_calls`, `tool_results`, `usage`, `response_meta` (decoded JSON objects).
 *
 * Tool rounds are not expanded into multiple package messages (unlike SDK reload); data is preserved in metadata
 * for observability and future Phase 6 attachment replay work.
 */
final readonly class LegacyLaravelAiDatabaseConversationReader
{
    public function __construct(
        private DatabaseManager $database,
        private ?string $connectionName,
        private string $conversationsTable,
        private string $messagesTable,
    ) {
    }

    /**
     * @throws DateMalformedStringException
     */
    public function find(ConversationId $conversationId): ?Conversation
    {
        $conversationUuid = $conversationId->toString();

        $conv = $this->connection()
            ->table($this->conversationsTable)
            ->where('id', $conversationUuid)
            ->first();

        if ($conv === null) {
            return null;
        }

        $title = $this->stringField($conv, 'title', '');
        $userId = $conv->user_id ?? null;
        $createdAt = $this->requireDateString($conv, 'created_at');
        $updatedAt = $this->requireDateString($conv, 'updated_at');

        $rows = $this->connection()
            ->table($this->messagesTable)
            ->where('conversation_id', $conversationUuid)
            ->orderBy('id')
            ->get();

        $messages = [];

        foreach ($rows as $row) {
            $messageId = $this->stringField($row, 'id', '');
            if ($messageId === '') {
                continue;
            }

            $roleRaw = strtolower($this->stringField($row, 'role', ''));
            $role = match ($roleRaw) {
                'user' => ConversationMessageRole::User,
                'assistant' => ConversationMessageRole::Assistant,
                default => ConversationMessageRole::User,
            };

            $content = $this->stringField($row, 'content', '');
            $messageCreatedAt = $this->requireDateString($row, 'created_at');
            $agent = $this->optionalStringField($row, 'agent');

            $metadata = [
                'laravel_ai' => [
                    'message_row_id' => $messageId,
                    'agent' => $agent,
                ],
            ];

            if ($role === ConversationMessageRole::User) {
                $attachments = $this->jsonArrayField($row, 'attachments');
                if ($attachments !== []) {
                    $metadata['laravel_ai']['attachments'] = $attachments;
                }
            }

            if ($role === ConversationMessageRole::Assistant) {
                $toolCalls = $this->jsonArrayField($row, 'tool_calls');
                $toolResults = $this->jsonArrayField($row, 'tool_results');
                $usage = $this->jsonArrayField($row, 'usage');
                $meta = $this->jsonArrayField($row, 'meta');
                if ($toolCalls !== []) {
                    $metadata['laravel_ai']['tool_calls'] = $toolCalls;
                }
                if ($toolResults !== []) {
                    $metadata['laravel_ai']['tool_results'] = $toolResults;
                }
                if ($usage !== []) {
                    $metadata['laravel_ai']['usage'] = $usage;
                }
                if ($meta !== []) {
                    $metadata['laravel_ai']['response_meta'] = $meta;
                }
            }

            $messages[] = new ConversationMessage(
                id: new MessageId($messageId),
                role: $role,
                content: $content,
                createdAt: new DateTimeImmutable($messageCreatedAt),
                metadata: $metadata,
            );
        }

        $conversationMetadata = [
            'laravel_ai' => [
                'title' => $title,
                'user_id' => $userId,
            ],
        ];

        return new Conversation(
            id: $conversationId,
            createdAt: new DateTimeImmutable($createdAt),
            updatedAt: new DateTimeImmutable($updatedAt),
            messages: $messages,
            metadata: $conversationMetadata,
        );
    }

    private function connection(): Connection
    {
        return $this->database->connection($this->connectionName);
    }

    private function requireDateString(object $record, string $field): string
    {
        $value = $record->{$field} ?? null;

        if ($value instanceof DateTimeImmutable) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    private function stringField(object $record, string $field, string $default): string
    {
        $value = $record->{$field} ?? null;

        return is_string($value) ? $value : $default;
    }

    private function optionalStringField(object $record, string $field): ?string
    {
        $value = $record->{$field} ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonArrayField(object $record, string $field): array
    {
        $raw = $record->{$field} ?? null;

        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_array($raw)) {
            /** @var array<string, mixed> $raw */
            return $raw;
        }

        if (!is_string($raw)) {
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

        /** @var array<string, mixed> $normalized */
        $normalized = [];

        foreach ($decoded as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }
}
