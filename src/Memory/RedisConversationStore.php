<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Memory;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationRetentionPurger;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\EncryptionService;
use CreativeCrafts\LaravelAiAgentKit\Memory\Exceptions\ConversationStoreException;
use CreativeCrafts\LaravelAiAgentKit\Memory\Exceptions\ConversationWriteConflictException;
use DateMalformedStringException;
use DateTimeImmutable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use JsonException;
use RuntimeException;
use Throwable;

final readonly class RedisConversationStore implements ConversationRetentionPurger, ConversationStore
{
    private const string COMPARE_AND_SET_SCRIPT = <<<'LUA'
local current = redis.call('GET', KEYS[1])
local expected = tonumber(ARGV[1])
local ttl = tonumber(ARGV[4])

local function store(value)
    if ttl ~= nil and ttl > 0 then
        redis.call('SET', KEYS[1], value, 'EX', ttl)
    else
        redis.call('SET', KEYS[1], value)
    end
end

if current == false then
    if expected ~= 0 then
        return 0
    end

    store(ARGV[2])
    return 1
end

local decodedOk, decoded = pcall(cjson.decode, current)
if not decodedOk or type(decoded) ~= 'table' then
    return -2
end

local actual = tonumber(decoded.revision or 0)
if actual ~= expected then
    return 0
end

store(ARGV[3])
return 2
LUA;

    private EncryptionService $encryptionService;

    private bool $encryptPayloads;

    public function __construct(
        private Application $app,
        private ?string $connectionName,
        private string $keyPrefix,
        private string $driverName,
        private ?int $retentionDays = null,
        ?EncryptionService $encryptionService = null,
        ?bool $encryptPayloads = null,
    ) {
        if (!$this->app->bound('redis')) {
            throw new RuntimeException('Redis memory driver requires a bound [redis] service in the container.');
        }

        $this->encryptionService = $encryptionService ?? $this->app->make(EncryptionService::class);

        if ($encryptPayloads !== null) {
            $this->encryptPayloads = $encryptPayloads;

            return;
        }

        $configuredEncryptPayloads = $this->app->make('config')->get('ai-agent-kit.memory.redis.encrypt_payloads', true);
        if (!is_bool($configuredEncryptPayloads)) {
            throw new RuntimeException('Configuration key [ai-agent-kit.memory.redis.encrypt_payloads] must be a boolean.');
        }

        $this->encryptPayloads = $configuredEncryptPayloads;
    }

    /**
     * @throws DateMalformedStringException
     * @throws Throwable
     */
    public function find(ConversationId $conversationId): ?Conversation
    {
        $payload = $this->getValue($this->keyFor($conversationId));

        if ($payload === null) {
            return null;
        }

        if (!is_string($payload)) {
            throw ConversationStoreException::payloadDecodingFailed(
                'redis.payload',
                new RuntimeException('Redis payload must be a string.'),
            );
        }

        $decoded = $this->decodePayload($payload);

        if ($this->isExpired($decoded)) {
            $this->delete($conversationId);

            return null;
        }

        return $this->hydrateConversation($decoded);
    }

    /**
     * @throws Throwable
     */
    public function delete(ConversationId $conversationId): void
    {
        $this->deleteKey($this->keyFor($conversationId));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Throwable
     */
    public function save(Conversation $conversation): void
    {
        $payload = [
            'conversation_id' => $conversation->id->toString(),
            'revision' => $conversation->revision,
            'driver' => $this->driverName,
            'retention_until' => $this->retentionTimestamp($conversation),
            'created_at' => $conversation->createdAt->format(DATE_ATOM),
            'updated_at' => $conversation->updatedAt->format(DATE_ATOM),
            'metadata' => $conversation->metadata,
            'messages' => array_map(
                function (ConversationMessage $message): array {
                    [$meta, $attachments] = $this->splitAttachmentsFromMetadata($message->metadata);

                    return [
                        'id' => $message->id->toString(),
                        'role' => $message->role->value,
                        'content' => $message->content,
                        'created_at' => $message->createdAt->format(DATE_ATOM),
                        'metadata' => $meta,
                        'attachments' => $attachments,
                    ];
                },
                $conversation->messages,
            ),
        ];

        try {
            $initialPayload = $payload;
            $initialPayload['revision'] = 0;
            $updatedPayload = $payload;
            $updatedPayload['revision'] = $conversation->revision + 1;
            $encodedInitialPayload = json_encode($initialPayload, JSON_THROW_ON_ERROR);
            $encodedUpdatedPayload = json_encode($updatedPayload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw ConversationStoreException::payloadEncodingFailed('redis.payload', $exception);
        }

        $this->compareAndSetValue(
            key: $this->keyFor($conversation->id),
            conversationId: $conversation->id->toString(),
            expectedRevision: $conversation->revision,
            initialValue: $this->encodeStoredPayload($encodedInitialPayload, 0),
            updatedValue: $this->encodeStoredPayload($encodedUpdatedPayload, $conversation->revision + 1),
            ttlSeconds: $this->retentionTtlSeconds($conversation),
        );
    }

    /**
     * @throws DateMalformedStringException
     * @throws Throwable
     */
    public function purgeExpired(?DateTimeImmutable $now = null): int
    {
        $purged = 0;
        $threshold = $now ?? new DateTimeImmutable();

        foreach ($this->scanKeys($this->pattern()) as $key) {
            $payload = $this->getValue($key);

            if (!is_string($payload)) {
                continue;
            }

            $decoded = $this->decodePayload($payload);
            $retentionUntil = $decoded['retention_until'] ?? null;
            if (!is_string($retentionUntil)) {
                continue;
            }
            if ($retentionUntil === '') {
                continue;
            }

            if ($this->date($retentionUntil, 'redis.payload.retention_until') <= $threshold) {
                $this->deleteKey($key);
                $purged++;
            }
        }

        return $purged;
    }

    /**
     * @param list<mixed> $arguments
     * @throws BindingResolutionException
     * @throws Throwable
     */
    private function command(string $name, array $arguments): mixed
    {
        return $this->app
            ->make('redis')
            ->connection($this->connectionName)
            ->command($name, $arguments);
    }

    /**
     * @throws BindingResolutionException
     * @throws Throwable
     */
    private function getValue(string $key): mixed
    {
        return $this->command('GET', [$key]);
    }

    private function keyFor(ConversationId $conversationId): string
    {
        return "{$this->keyPrefix}{$conversationId->toString()}";
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(string $payload): array
    {
        $decoded = $this->decodeJsonPayload($payload, 'redis.payload');

        if (($decoded['encrypted'] ?? false) !== true) {
            return $decoded;
        }

        $encryptedPayload = $decoded['payload'] ?? null;
        if (!is_string($encryptedPayload) || $encryptedPayload === '') {
            throw ConversationStoreException::payloadDecodingFailed(
                'redis.payload.payload',
                new RuntimeException('Encrypted Redis payload must contain a non-empty payload string.'),
            );
        }

        try {
            $plaintext = $this->encryptionService->decryptString($encryptedPayload);
        } catch (Throwable $throwable) {
            throw ConversationStoreException::payloadDecryptionFailed('redis.payload.payload', $throwable);
        }

        $decrypted = $this->decodeJsonPayload($plaintext, 'redis.payload.decrypted');
        $decrypted['revision'] = $decoded['revision'] ?? 0;

        return $decrypted;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonPayload(string $payload, string $field): array
    {
        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw ConversationStoreException::payloadDecodingFailed($field, $exception);
        }

        if (!is_array($decoded)) {
            throw ConversationStoreException::payloadDecodingFailed(
                $field,
                new RuntimeException('Decoded Redis payload must be an array.'),
            );
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @throws ConversationStoreException
     */
    private function encodeStoredPayload(string $encodedPayload, int $revision): string
    {
        if (!$this->encryptPayloads) {
            return $encodedPayload;
        }

        try {
            return json_encode([
                'encrypted' => true,
                'revision' => $revision,
                'payload' => $this->encryptionService->encryptString($encodedPayload),
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw ConversationStoreException::payloadEncodingFailed('redis.payload.encrypted', $exception);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @throws DateMalformedStringException
     */
    private function isExpired(array $payload): bool
    {
        $retentionUntil = $payload['retention_until'] ?? null;

        if (!is_string($retentionUntil) || $retentionUntil === '') {
            return false;
        }

        return $this->date($retentionUntil, 'redis.payload.retention_until') <= new DateTimeImmutable();
    }

    /**
     * @throws Throwable
     */
    private function deleteKey(string $key): void
    {
        $this->command('DEL', [$key]);
    }

    /**
     * @param array<string, mixed> $payload
     * @throws DateMalformedStringException
     */
    private function hydrateConversation(array $payload): Conversation
    {
        $messages = [];

        if (!array_key_exists('messages', $payload)) {
            throw ConversationStoreException::payloadDecodingFailed(
                'redis.payload.messages',
                new RuntimeException('Redis conversation payload requires a messages field.'),
            );
        }

        $rawMessages = $payload['messages'];
        if (!is_array($rawMessages)) {
            throw ConversationStoreException::payloadDecodingFailed(
                'redis.payload.messages',
                new RuntimeException('Redis messages payload must be an array.'),
            );
        }

        foreach ($rawMessages as $index => $rawMessage) {
            $messagePayload = $this->requireArrayPayload($rawMessage, "redis.payload.messages.{$index}");

            $messageId = $this->requireString($messagePayload, 'id', "redis.payload.messages.{$index}.id");
            $role = $this->requireString($messagePayload, 'role', "redis.payload.messages.{$index}.role");
            $content = $this->requireString($messagePayload, 'content', "redis.payload.messages.{$index}.content");
            $createdAt = $this->requireString($messagePayload, 'created_at', "redis.payload.messages.{$index}.created_at");

            $metadata = $messagePayload['metadata'] ?? [];
            if (!is_array($metadata)) {
                throw ConversationStoreException::payloadDecodingFailed(
                    "redis.payload.messages.{$index}.metadata",
                    new RuntimeException('Redis message metadata must be an array.'),
                );
            }

            /** @var array<string, mixed> $metadata */
            $attachments = $messagePayload['attachments'] ?? [];
            if (!is_array($attachments)) {
                throw ConversationStoreException::payloadDecodingFailed(
                    "redis.payload.messages.{$index}.attachments",
                    new RuntimeException('Redis message attachments must be an array.'),
                );
            }

            foreach ($attachments as $attachment) {
                if (!is_array($attachment)) {
                    throw ConversationStoreException::payloadDecodingFailed(
                        "redis.payload.messages.{$index}.attachments",
                        new RuntimeException('Redis message attachment entries must be arrays.'),
                    );
                }
            }

            if ($attachments !== []) {
                $metadata['attachments'] = array_values($attachments);
            }

            $messages[] = new ConversationMessage(
                id: $this->messageId($messageId, "redis.payload.messages.{$index}.id"),
                role: $this->messageRole($role, "redis.payload.messages.{$index}.role"),
                content: $content,
                createdAt: $this->date($createdAt, "redis.payload.messages.{$index}.created_at"),
                metadata: $metadata,
            );
        }

        $conversationId = $this->requireString($payload, 'conversation_id', 'redis.payload.conversation_id');
        $createdAt = $this->requireString($payload, 'created_at', 'redis.payload.created_at');
        $updatedAt = $this->requireString($payload, 'updated_at', 'redis.payload.updated_at');

        $metadata = $payload['metadata'] ?? [];
        if (!is_array($metadata)) {
            throw ConversationStoreException::payloadDecodingFailed(
                'redis.payload.metadata',
                new RuntimeException('Redis conversation metadata must be an array.'),
            );
        }

        /** @var array<string, mixed> $metadata */
        return new Conversation(
            id: $this->conversationId($conversationId, 'redis.payload.conversation_id'),
            createdAt: $this->date($createdAt, 'redis.payload.created_at'),
            updatedAt: $this->date($updatedAt, 'redis.payload.updated_at'),
            messages: $messages,
            metadata: $metadata,
            revision: $this->requireRevision($payload),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requireRevision(array $payload): int
    {
        $revision = $payload['revision'] ?? 0;

        if (is_int($revision) && $revision >= 0) {
            return $revision;
        }

        throw ConversationStoreException::payloadDecodingFailed(
            'redis.payload.revision',
            new RuntimeException('Redis conversation revision must be a non-negative integer.'),
        );
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

    /**
     * @return array<string, mixed>
     */
    private function requireArrayPayload(mixed $payload, string $field): array
    {
        if (!is_array($payload)) {
            throw ConversationStoreException::payloadDecodingFailed(
                $field,
                new RuntimeException('Redis payload segment must be an array.'),
            );
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requireString(array $payload, string $key, string $field): string
    {
        $value = $payload[$key] ?? null;

        if (!is_string($value) || $value === '') {
            throw ConversationStoreException::payloadDecodingFailed(
                $field,
                new RuntimeException("Redis payload field [{$field}] must be a non-empty string."),
            );
        }

        return $value;
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
            ->format(DATE_ATOM);
    }

    /**
     * @throws DateMalformedStringException
     */
    private function retentionTtlSeconds(Conversation $conversation): ?int
    {
        if ($this->retentionDays === null) {
            return null;
        }

        $expiresAt = $conversation->updatedAt->modify(sprintf('+%d days', $this->retentionDays));
        $seconds = $expiresAt->getTimestamp() - (new DateTimeImmutable())->getTimestamp();

        return max(1, $seconds);
    }

    /**
     * @throws Throwable
     */
    private function compareAndSetValue(
        string $key,
        string $conversationId,
        int $expectedRevision,
        string $initialValue,
        string $updatedValue,
        ?int $ttlSeconds = null,
    ): void {
        $result = $this->command('EVAL', [
            self::COMPARE_AND_SET_SCRIPT,
            1,
            $key,
            (string)$expectedRevision,
            $initialValue,
            $updatedValue,
            $ttlSeconds === null ? '' : (string)$ttlSeconds,
        ]);

        if (in_array($result, [1, 2, '1', '2'], true)) {
            return;
        }

        if ($result === -2 || $result === '-2') {
            throw ConversationStoreException::payloadDecodingFailed(
                'redis.payload.revision',
                new RuntimeException('Stored Redis conversation payload is not valid JSON.'),
            );
        }

        throw ConversationWriteConflictException::forRevision(
            conversationId: $conversationId,
            expectedRevision: $expectedRevision,
        );
    }

    /**
     * @return iterable<string>
     * @throws Throwable
     */
    private function scanKeys(string $pattern, int $count = 100): iterable
    {
        $cursor = '0';

        do {
            $response = $this->command('SCAN', [$cursor, 'MATCH', $pattern, 'COUNT', $count]);

            if (!is_array($response) || count($response) !== 2) {
                return;
            }

            [$cursor, $keys] = $response;

            if (is_array($keys)) {
                foreach ($keys as $key) {
                    if (is_string($key)) {
                        yield $key;
                    }
                }
            }
        } while ($cursor !== '0');
    }

    private function pattern(): string
    {
        return "{$this->keyPrefix}*";
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array{0: array<string, mixed>, 1: array<int, mixed>}
     */
    private function splitAttachmentsFromMetadata(array $metadata): array
    {
        if (!array_key_exists('attachments', $metadata)) {
            return [$metadata, []];
        }

        $attachments = $metadata['attachments'];
        unset($metadata['attachments']);

        if (!is_array($attachments)) {
            return [$metadata, []];
        }

        return [$metadata, array_values($attachments)];
    }
}
