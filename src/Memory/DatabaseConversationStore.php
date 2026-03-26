<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Memory;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\EncryptionService;
use CreativeCrafts\LaravelAiAgentKit\Memory\Exceptions\ConversationStoreException;
use DateMalformedStringException;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use JsonException;
use RuntimeException;
use Throwable;

final readonly class DatabaseConversationStore implements ConversationStore
{
    public function __construct(
        private DatabaseManager $database,
        private EncryptionService $encryptionService,
        private ?string $connectionName,
        private string $conversationsTable,
        private string $messagesTable,
        private string $driverName,
        private ?int $retentionDays,
        private bool $encryptPayloads = true,
    ) {
    }

    /**
     * @throws DateMalformedStringException
     */
    public function find(ConversationId $conversationId): ?Conversation
    {
        $record = $this
          ->connection()
          ->table($this->conversationsTable)
          ->where('conversation_id', $conversationId->toString())
          ->whereNull('deleted_at')
          ->first();

        if ($record === null) {
            return null;
        }

        $messages = $this
          ->connection()
          ->table($this->messagesTable)
          ->where('conversation_record_id', $record->id)
          ->orderBy('sequence')
          ->get();

        $conversationMessages = [];

        foreach ($messages as $messageRecord) {
            $messageId = $this->requireStringValue($messageRecord, 'message_id');
            $role = $this->requireStringValue($messageRecord, 'role');
            $contentCiphertext = $this->requireStringValue($messageRecord, 'content_ciphertext');
            $createdAt = $this->requireStringValue($messageRecord, 'created_at');

            $conversationMessages[] = new ConversationMessage(
                id: new MessageId($messageId),
                role: ConversationMessageRole::from($role),
                content: $this->decodeStringPayload(
                    field: 'messages.content_ciphertext',
                    payload: $contentCiphertext,
                    isEncrypted: (bool)$record->is_encrypted,
                ),
                createdAt: new DateTimeImmutable($createdAt),
                metadata: $this->decodeArrayPayload(
                    field: 'messages.metadata_ciphertext',
                    payload: $messageRecord->metadata_ciphertext,
                    isEncrypted: (bool)$record->is_encrypted,
                ),
            );
        }

        $conversationRecordId = $this->requireStringValue($record, 'conversation_id');
        $conversationCreatedAt = $this->requireStringValue($record, 'created_at');
        $conversationUpdatedAt = $this->requireStringValue($record, 'updated_at');

        return new Conversation(
            id: new ConversationId($conversationRecordId),
            createdAt: new DateTimeImmutable($conversationCreatedAt),
            updatedAt: new DateTimeImmutable($conversationUpdatedAt),
            messages: $conversationMessages,
            metadata: $this->decodeArrayPayload(
                field: 'conversations.metadata_ciphertext',
                payload: $record->metadata_ciphertext,
                isEncrypted: (bool)$record->is_encrypted,
            ),
        );
    }

    /**
     * @throws Throwable
     */
    public function save(Conversation $conversation): void
    {
        $connection = $this->connection();

        $connection->transaction(function () use ($connection, $conversation): void {
            $existingRecordIdValue = $connection
              ->table($this->conversationsTable)
              ->where('conversation_id', $conversation->id->toString())
              ->value('id');

            $existingRecordId = $existingRecordIdValue === null
              ? null
              : $this->normalizeIntValue($existingRecordIdValue, 'conversations.id');

            $conversationPayload = [
              'conversation_id' => $conversation->id->toString(),
              'driver' => $this->driverName,
              'store_conversation' => true,
              'is_encrypted' => $this->encryptPayloads,
              'retention_until' => $this->retentionTimestamp($conversation),
              'last_message_at' => $conversation->latestMessage()?->createdAt->format('Y-m-d H:i:s'),
              'summary_ciphertext' => null,
              'metadata_ciphertext' => $this->encodeArrayPayload('conversations.metadata_ciphertext', $conversation->metadata),
              'created_at' => $conversation->createdAt->format('Y-m-d H:i:s'),
              'updated_at' => $conversation->updatedAt->format('Y-m-d H:i:s'),
              'deleted_at' => null,
            ];

            if ($existingRecordId === null) {
                $conversationRecordId = $this->normalizeIntValue(
                    $connection
                    ->table($this->conversationsTable)
                    ->insertGetId($conversationPayload),
                    'conversations.id',
                );
            } else {
                $connection
                  ->table($this->conversationsTable)
                  ->where('id', $existingRecordId)
                  ->update($conversationPayload);

                $conversationRecordId = $existingRecordId;
            }

            $existingMessageIds = $connection
              ->table($this->messagesTable)
              ->where('conversation_record_id', $conversationRecordId)
              ->pluck('message_id')
              ->all();

            /** @var list<string> $existingMessageIds */
            $existingMessageIds = array_values(array_filter($existingMessageIds, static fn (mixed $id): bool => is_string($id) && $id !== ''));

            $incomingMessageIds = [];
            $newRows = [];

            foreach ($conversation->messages as $index => $message) {
                $messageId = $message->id->toString();
                $incomingMessageIds[] = $messageId;

                if (in_array($messageId, $existingMessageIds, true)) {
                    continue;
                }

                $newRows[] = [
                  'conversation_record_id' => $conversationRecordId,
                  'message_id' => $messageId,
                  'sequence' => $index + 1,
                  'role' => $message->role->value,
                  'content_ciphertext' => $this->encodeStringPayload('messages.content_ciphertext', $message->content),
                  'metadata_ciphertext' => $this->encodeArrayPayload('messages.metadata_ciphertext', $message->metadata),
                  'token_count' => null,
                  'created_at' => $message->createdAt->format('Y-m-d H:i:s'),
                  'updated_at' => null,
                ];
            }

            $messageIdsToDelete = array_values(array_diff($existingMessageIds, $incomingMessageIds));

            if ($messageIdsToDelete !== []) {
                $connection
                  ->table($this->messagesTable)
                  ->where('conversation_record_id', $conversationRecordId)
                  ->whereIn('message_id', $messageIdsToDelete)
                  ->delete();
            }

            if ($newRows !== []) {
                $connection->table($this->messagesTable)->insert($newRows);
            }
        });
    }

    public function delete(ConversationId $conversationId): void
    {
        $this
          ->connection()
          ->table($this->conversationsTable)
          ->where('conversation_id', $conversationId->toString())
          ->whereNull('deleted_at')
          ->update([
            'deleted_at' => date('Y-m-d H:i:s'),
          ]);
    }

    private function connection(): Connection
    {
        return $this->database->connection($this->connectionName);
    }

    private function requireStringValue(object $record, string $field): string
    {
        if (!isset($record->{$field}) || !is_string($record->{$field})) {
            throw ConversationStoreException::payloadDecodingFailed(
                $field,
                new RuntimeException("Record field [{$field}] must be a string."),
            );
        }

        return $record->{$field};
    }

    private function decodeStringPayload(string $field, string $payload, bool $isEncrypted): string
    {
        if (!$isEncrypted) {
            return $payload;
        }

        try {
            return $this->encryptionService->decryptString($payload);
        } catch (Throwable $exception) {
            throw ConversationStoreException::payloadDecryptionFailed($field, $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeArrayPayload(string $field, mixed $payload, bool $isEncrypted): array
    {
        if ($payload === null || $payload === '') {
            return [];
        }

        if (!is_string($payload)) {
            throw ConversationStoreException::payloadDecodingFailed(
                $field,
                new RuntimeException("Payload for [{$field}] must be a string or null."),
            );
        }

        $decodedPayload = $this->decodeStringPayload($field, $payload, $isEncrypted);

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($decodedPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw ConversationStoreException::payloadDecodingFailed($field, $exception);
        }

        return $data;
    }

    private function normalizeIntValue(mixed $value, string $field): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int)$value;
        }

        throw ConversationStoreException::payloadDecodingFailed(
            $field,
            new RuntimeException("Record field [{$field}] must be an integer-compatible value."),
        );
    }

    /**
     * @throws DateMalformedStringException
     */
    private function retentionTimestamp(Conversation $conversation): ?string
    {
        if ($this->retentionDays === null) {
            return null;
        }

        return $conversation
          ->updatedAt
          ->modify(sprintf('+%d days', $this->retentionDays))
          ->format('Y-m-d H:i:s');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encodeArrayPayload(string $field, array $payload): ?string
    {
        if ($payload === []) {
            return null;
        }

        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw ConversationStoreException::payloadEncodingFailed($field, $exception);
        }

        return $this->encryptPayloads
          ? $this->encryptStringPayload($field, $json)
          : $json;
    }

    private function encryptStringPayload(string $field, string $payload): string
    {
        try {
            return $this->encryptionService->encryptString($payload);
        } catch (Throwable $exception) {
            throw ConversationStoreException::payloadEncryptionFailed($field, $exception);
        }
    }

    private function encodeStringPayload(string $field, string $payload): string
    {
        return $this->encryptPayloads
          ? $this->encryptStringPayload($field, $payload)
          : $payload;
    }
}
