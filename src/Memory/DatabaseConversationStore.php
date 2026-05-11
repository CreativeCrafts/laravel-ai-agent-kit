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

            $metadata = $this->decodeArrayPayload(
                field: 'messages.metadata_ciphertext',
                payload: $messageRecord->metadata_ciphertext,
                isEncrypted: (bool)$record->is_encrypted,
            );

            $attachmentsPayload = $messageRecord->attachments_ciphertext ?? null;
            if (is_string($attachmentsPayload) && $attachmentsPayload !== '') {
                $decoded = $this->decodeArrayPayload(
                    field: 'messages.attachments_ciphertext',
                    payload: $attachmentsPayload,
                    isEncrypted: (bool)$record->is_encrypted,
                );
                $attachmentRows = $decoded['attachments'] ?? null;
                if (is_array($attachmentRows) && $attachmentRows !== []) {
                    /** @var list<array<string, mixed>> $normalizedRows */
                    $normalizedRows = [];
                    foreach ($attachmentRows as $row) {
                        if (!is_array($row)) {
                            continue;
                        }

                        /** @var array<string, mixed> $assoc */
                        $assoc = [];
                        foreach ($row as $key => $value) {
                            $assoc[(string) $key] = $value;
                        }

                        $normalizedRows[] = $assoc;
                    }

                    if ($normalizedRows !== []) {
                        $metadata['attachments'] = $normalizedRows;
                    }
                }
            }

            $conversationMessages[] = new ConversationMessage(
                id: new MessageId($messageId),
                role: ConversationMessageRole::from($role),
                content: $this->decodeStringPayload(
                    field: 'messages.content_ciphertext',
                    payload: $contentCiphertext,
                    isEncrypted: (bool)$record->is_encrypted,
                ),
                createdAt: new DateTimeImmutable($createdAt),
                metadata: $metadata,
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
            $conversationRecordId = $this->upsertConversationRecord($connection, $conversation);

            $this->upsertConversationMessages($connection, $conversationRecordId, $conversation);
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

    /**
     * @throws DateMalformedStringException
     */
    private function upsertConversationRecord(Connection $connection, Conversation $conversation): int
    {
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

        $connection
            ->table($this->conversationsTable)
            ->upsert(
                [$conversationPayload],
                ['conversation_id'],
                [
                    'driver',
                    'store_conversation',
                    'is_encrypted',
                    'retention_until',
                    'last_message_at',
                    'summary_ciphertext',
                    'metadata_ciphertext',
                    'updated_at',
                    'deleted_at',
                ],
            );

        return $this->normalizeIntValue(
            $connection
                ->table($this->conversationsTable)
                ->where('conversation_id', $conversation->id->toString())
                ->value('id'),
            'conversations.id',
        );
    }

    private function upsertConversationMessages(Connection $connection, int $conversationRecordId, Conversation $conversation): void
    {
        if ($conversation->messages === []) {
            $connection
                ->table($this->messagesTable)
                ->where('conversation_record_id', $conversationRecordId)
                ->delete();

            return;
        }

        $incomingMessageIds = [];
        $rows = [];

        foreach ($conversation->messages as $index => $message) {
            $messageId = $message->id->toString();
            $incomingMessageIds[] = $messageId;
            [$metadataForStorage, $attachmentsList] = $this->splitMessageMetadataAndAttachments($message->metadata);

            $rows[] = [
              'conversation_record_id' => $conversationRecordId,
              'message_id' => $messageId,
              'sequence' => $index + 1,
              'role' => $message->role->value,
              'content_ciphertext' => $this->encodeStringPayload('messages.content_ciphertext', $message->content),
              'metadata_ciphertext' => $this->encodeArrayPayload('messages.metadata_ciphertext', $metadataForStorage),
              'attachments_ciphertext' => $this->encodeArrayPayload(
                  'messages.attachments_ciphertext',
                  $attachmentsList === [] ? [] : ['attachments' => $attachmentsList],
              ),
              'token_count' => null,
              'created_at' => $message->createdAt->format('Y-m-d H:i:s'),
              'updated_at' => $message->createdAt->format('Y-m-d H:i:s'),
            ];
        }

        $sequenceOffset = count($rows) + 1000000;
        $connection
            ->table($this->messagesTable)
            ->where('conversation_record_id', $conversationRecordId)
            ->update([
                'sequence' => $connection->raw('sequence + ' . $sequenceOffset),
            ]);

        $connection
            ->table($this->messagesTable)
            ->where('conversation_record_id', $conversationRecordId)
            ->whereNotIn('message_id', $incomingMessageIds)
            ->delete();

        $connection
            ->table($this->messagesTable)
            ->upsert(
                $rows,
                ['conversation_record_id', 'message_id'],
                [
                    'sequence',
                    'role',
                    'content_ciphertext',
                    'metadata_ciphertext',
                    'attachments_ciphertext',
                    'token_count',
                    'updated_at',
                ],
            );
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

    /**
     * @param array<string, mixed> $metadata
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function splitMessageMetadataAndAttachments(array $metadata): array
    {
        if (!array_key_exists('attachments', $metadata)) {
            return [$metadata, []];
        }

        $attachments = $metadata['attachments'];
        unset($metadata['attachments']);

        if (!is_array($attachments) || $attachments === []) {
            return [$metadata, []];
        }

        /** @var array<int, array<string, mixed>> $normalized */
        $normalized = [];

        foreach ($attachments as $row) {
            if (!is_array($row)) {
                continue;
            }

            /** @var array<string, mixed> $assoc */
            $assoc = [];
            foreach ($row as $key => $value) {
                $assoc[(string) $key] = $value;
            }

            $normalized[] = $assoc;
        }

        return [$metadata, $normalized];
    }

    private function encodeStringPayload(string $field, string $payload): string
    {
        return $this->encryptPayloads
          ? $this->encryptStringPayload($field, $payload)
          : $payload;
    }
}
