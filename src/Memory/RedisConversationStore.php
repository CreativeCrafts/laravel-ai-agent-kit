<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Memory;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationRetentionPurger;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\Exceptions\ConversationStoreException;
use DateMalformedStringException;
use DateTimeImmutable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use JsonException;
use RuntimeException;
use Throwable;

final readonly class RedisConversationStore implements ConversationRetentionPurger, ConversationStore
{
    public function __construct(
        private Application $app,
        private ?string $connectionName,
        private string $keyPrefix,
        private string $driverName,
        private ?int $retentionDays = null,
    ) {
        if (!$this->app->bound('redis')) {
            throw new RuntimeException('Redis memory driver requires a bound [redis] service in the container.');
        }
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
          'driver' => $this->driverName,
          'retention_until' => $this->retentionTimestamp($conversation),
          'created_at' => $conversation->createdAt->format(DATE_ATOM),
          'updated_at' => $conversation->updatedAt->format(DATE_ATOM),
          'metadata' => $conversation->metadata,
          'messages' => array_map(
              static fn (ConversationMessage $message): array
                => [
              'id' => $message->id->toString(),
              'role' => $message->role->value,
              'content' => $message->content,
              'created_at' => $message->createdAt->format(DATE_ATOM),
              'metadata' => $message->metadata,
            ],
              $conversation->messages,
          ),
        ];

        try {
            $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw ConversationStoreException::payloadEncodingFailed('redis.payload', $exception);
        }

        $this->setValue($this->keyFor($conversation->id), $encodedPayload);
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

            if (new DateTimeImmutable($retentionUntil) <= $threshold) {
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
        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw ConversationStoreException::payloadDecodingFailed('redis.payload', $exception);
        }

        if (!is_array($decoded)) {
            throw ConversationStoreException::payloadDecodingFailed(
                'redis.payload',
                new RuntimeException('Decoded Redis payload must be an array.'),
            );
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
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

        return new DateTimeImmutable($retentionUntil) <= new DateTimeImmutable();
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

        $rawMessages = $payload['messages'] ?? [];
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
            $messages[] = new ConversationMessage(
                id: new MessageId($messageId),
                role: ConversationMessageRole::from($role),
                content: $content,
                createdAt: new DateTimeImmutable($createdAt),
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
            id: new ConversationId($conversationId),
            createdAt: new DateTimeImmutable($createdAt),
            updatedAt: new DateTimeImmutable($updatedAt),
            messages: $messages,
            metadata: $metadata,
        );
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
     * @throws Throwable
     */
    private function setValue(string $key, string $value): void
    {
        $this->command('SET', [$key, $value]);
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
}
