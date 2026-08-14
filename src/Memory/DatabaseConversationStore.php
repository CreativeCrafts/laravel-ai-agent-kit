<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Memory;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\EncryptionService;
use CreativeCrafts\LaravelAiAgentKit\Memory\Exceptions\ConversationStoreException;
use CreativeCrafts\LaravelAiAgentKit\Memory\Exceptions\ConversationWriteConflictException;
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
                $attachmentRows = $decoded['attachments'] ?? [];
                if (!is_array($attachmentRows)) {
                    throw ConversationStoreException::payloadDecodingFailed(
                        'messages.attachments_ciphertext.attachments',
                        new RuntimeException('Persisted attachments must be an array.'),
                    );
                }

                if ($attachmentRows !== []) {
                    /** @var list<array<string, mixed>> $normalizedRows */
                    $normalizedRows = [];
                    foreach ($attachmentRows as $row) {
                        if (!is_array($row)) {
                            throw ConversationStoreException::payloadDecodingFailed(
                                'messages.attachments_ciphertext.attachments',
                                new RuntimeException('Persisted attachment entries must be arrays.'),
                            );
                        }

                        /** @var array<string, mixed> $assoc */
                        $assoc = [];
                        foreach ($row as $key => $value) {
                            $assoc[(string) $key] = $value;
                        }

                        $normalizedRows[] = $assoc;
                    }

                    $metadata['attachments'] = $normalizedRows;
                }
            }

            $conversationMessages[] = new ConversationMessage(
                id: $this->messageId($messageId, 'messages.message_id'),
                role: $this->messageRole($role, 'messages.role'),
                content: $this->decodeStringPayload(
                    field: 'messages.content_ciphertext',
                    payload: $contentCiphertext,
                    isEncrypted: (bool)$record->is_encrypted,
                ),
                createdAt: $this->date($createdAt, 'messages.created_at'),
                metadata: $metadata,
            );
        }

        $conversationRecordId = $this->requireStringValue($record, 'conversation_id');
        $conversationCreatedAt = $this->requireStringValue($record, 'created_at');
        $conversationUpdatedAt = $this->requireStringValue($record, 'updated_at');

        return new Conversation(
            id: $this->conversationId($conversationRecordId, 'conversations.conversation_id'),
            createdAt: $this->date($conversationCreatedAt, 'conversations.created_at'),
            updatedAt: $this->date($conversationUpdatedAt, 'conversations.updated_at'),
            messages: $conversationMessages,
            metadata: $this->decodeArrayPayload(
                field: 'conversations.metadata_ciphertext',
                payload: $record->metadata_ciphertext,
                isEncrypted: (bool)$record->is_encrypted,
            ),
            revision: $this->normalizeIntValue($record->revision ?? 0, 'conversations.revision'),
        );
    }

    /**
     * @throws Throwable
     */
    public function save(Conversation $conversation): void
    {
        $connection = $this->connection();

        $connection->transaction(function () use ($connection, $conversation): void {
            $conversationRecordId = $this->acquireConversationRevision($connection, $conversation);

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
    private function acquireConversationRevision(Connection $connection, Conversation $conversation): int
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

        $existing = $connection
            ->table($this->conversationsTable)
            ->where('conversation_id', $conversation->id->toString())
            ->first(['id', 'revision']);

        if ($existing === null) {
            if ($conversation->revision !== 0) {
                throw ConversationWriteConflictException::forRevision(
                    $conversation->id->toString(),
                    $conversation->revision,
                );
            }

            $inserted = $connection
                ->table($this->conversationsTable)
                ->insertOrIgnore([$conversationPayload + ['revision' => 0]]);

            if ($inserted !== 1) {
                throw ConversationWriteConflictException::forRevision(
                    $conversation->id->toString(),
                    $conversation->revision,
                );
            }
        } else {
            $actualRevision = $this->normalizeIntValue($existing->revision ?? 0, 'conversations.revision');
            $updatePayload = $conversationPayload;
            unset($updatePayload['created_at']);
            $updated = $connection
                ->table($this->conversationsTable)
                ->where('conversation_id', $conversation->id->toString())
                ->where('revision', $conversation->revision)
                ->update($updatePayload + ['revision' => $conversation->revision + 1]);

            if ($updated !== 1) {
                throw ConversationWriteConflictException::forRevision(
                    conversationId: $conversation->id->toString(),
                    expectedRevision: $conversation->revision,
                    actualRevision: $actualRevision,
                );
            }
        }

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

        $this->moveExistingMessageSequencesOutOfIncomingRange($connection, $conversationRecordId, count($rows));

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

    private function moveExistingMessageSequencesOutOfIncomingRange(
        Connection $connection,
        int $conversationRecordId,
        int $incomingMessageCount,
    ): void {
        $temporarySequence = $incomingMessageCount + 1000000;
        $recordIds = $connection
            ->table($this->messagesTable)
            ->where('conversation_record_id', $conversationRecordId)
            ->orderByDesc('sequence')
            ->pluck('id');

        foreach ($recordIds as $recordId) {
            $connection
                ->table($this->messagesTable)
                ->where('id', $recordId)
                ->update(['sequence' => $temporarySequence]);

            $temporarySequence++;
        }
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
            $data = json_decode($decodedPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw ConversationStoreException::payloadDecodingFailed($field, $exception);
        }

        if (!is_array($data)) {
            throw ConversationStoreException::payloadDecodingFailed(
                $field,
                new RuntimeException("Decoded payload for [{$field}] must be an array."),
            );
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    private function messageRole(string $role, string $field): ConversationMessageRole
    {
        try {
            return ConversationMessageRole::from($role);
        } catch (Throwable $throwable) {
            throw ConversationStoreException::payloadDecodingFailed($field, $throwable);
        }
    }

    private function date(string $value, string $field): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        } catch (Throwable $throwable) {
            throw ConversationStoreException::payloadDecodingFailed($field, $throwable);
        }
    }

    private function messageId(string $value, string $field): MessageId
    {
        try {
            return new MessageId($value);
        } catch (Throwable $throwable) {
            throw ConversationStoreException::payloadDecodingFailed($field, $throwable);
        }
    }

    private function conversationId(string $value, string $field): ConversationId
    {
        try {
            return new ConversationId($value);
        } catch (Throwable $throwable) {
            throw ConversationStoreException::payloadDecodingFailed($field, $throwable);
        }
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
