# Memory

## Optimistic conversation revisions

Every `Conversation` carries a non-negative persistence `revision`, defaulting to `0` for source and serialization compatibility. Database, Redis, and in-memory stores compare that revision atomically before replacing aggregate state. Saving one of two aggregates loaded at the same revision succeeds; saving the stale aggregate throws `ConversationWriteConflictException`. Agent Kit does not merge concurrent message graphs automatically.

Publish and run the `add_revision_to_ai_agent_conversations_table` migration when upgrading an existing database installation. Redis compare-and-set uses a Lua script, including for encrypted payloads; it does not perform a non-atomic read/compare/write sequence.

Malformed persisted JSON, roles, dates, required fields, attachments, and encrypted payloads are normalized to `ConversationStoreException` with a semantic field name and the original throwable as `previous`. Exception messages never include decrypted conversation content.

Memory lets workflows start or continue conversations through package-owned contracts. The package owns retention, persistence, encryption, and no-store policy; Laravel AI SDK conversation objects are not the public memory contract for Agent Kit workflows.

## Drivers

Configure the default memory driver in `config/ai-agent-kit.php`:

~~~php
'memory' => [
    'default_driver' => 'in_memory',
],
~~~

Available drivers:

- `in_memory`: process-local, non-persistent, useful for tests and local development.
- `database`: persistent storage with encrypted payload support, retention behavior, and idempotent writes.
- `redis`: shared ephemeral memory across workers, with encrypted payload support and Redis-native expiration.

## Store a conversation message

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessage;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessageRole;
use CreativeCrafts\LaravelAiAgentKit\Memory\MessageId;
use DateTimeImmutable;

final class SupportConversationService
{
    public function __construct(
        private ConversationStore $store,
    ) {
    }

    public function appendUserMessage(string $content): void
    {
        $conversationId = new ConversationId('conv-001');
        $now = new DateTimeImmutable();

        $conversation = $this->store->find($conversationId)
            ?? new Conversation(id: $conversationId, createdAt: $now, updatedAt: $now);

        $conversation = $conversation->withAppendedMessage(
            new ConversationMessage(
                id: new MessageId('msg-001'),
                role: ConversationMessageRole::User,
                content: $content,
                createdAt: $now,
            ),
            $now,
        );

        $this->store->save($conversation);
    }
}
~~~

## RunContext integration

Pipeline and runtime flows carry memory-related state through `RunContext`:

- `conversationId`
- `conversation`
- `storeConversation`
- `continueConversation`

Prefer passing a conversation ID when queued work can reload state in the worker. Avoid serializing a large full conversation graph into queued jobs unless required.

## Persistence and encryption

Use the database driver when you need durable conversation state. When database encryption is enabled, message payloads are encrypted before storage.

Database conversation persistence is idempotent by conversation ID. Saving the same `Conversation` repeatedly updates the existing conversation row instead of inserting duplicates. Message rows are idempotent per database conversation record and message ID, so saving the same message IDs again updates those rows instead of duplicating them.

Database writes use atomic database write semantics for the conversation row and message rows inside the save transaction. This prevents common unique-key races under queued or multi-worker workloads. It does not merge divergent histories automatically: if two workers save different conversation graphs for the same conversation ID, the final stored conversation follows normal last-write-wins database behavior for the rows each save writes.

Saving a previously soft-deleted database conversation restores it by clearing `deleted_at`.

Existing applications that published and ran the earlier conversation message migration must also publish and run the upgrade migration `update_ai_agent_conversation_messages_message_identity_index` (included in the `ai-agent-kit-migrations` tag). It removes the old global `message_id` unique index when present and adds the required unique index on `conversation_record_id` + `message_id`. New installs already receive the composite message identity from the current migration stub.

Redis memory encrypts the full stored conversation payload by default:

~~~php
'memory' => [
    'redis' => [
        'encrypt_payloads' => true,
    ],
],
~~~

The Redis encrypted value uses a small wrapper that identifies encrypted payloads and stores the encrypted JSON payload. The encrypted JSON contains the same conversation structure that plaintext Redis payloads used previously.

For compatibility, Redis memory can still read existing plaintext payloads written by earlier package versions. New writes use the configured mode. Set `encrypt_payloads` to `false` only when you explicitly accept that prompt content, assistant output, metadata, and attachment references are readable in Redis.

### Redis plaintext migration runbook

When upgrading from plaintext Redis payloads to encrypted storage:

1. Deploy with `memory.redis.encrypt_payloads` enabled (default).
2. Run a controlled migration window: load and re-save active conversations through Agent Kit so new writes use encrypted wrappers.
3. After re-save coverage, flush or expire legacy plaintext keys under your Redis prefix (or rely on configured `retention_days` TTL).
4. Rotate Redis credentials and review access controls after migration.
5. Verify backups and replicas no longer retain required plaintext payloads before decommissioning old keys.

Applications sharing Redis keys must use the same application encryption key to read encrypted payloads. Prefer separate Redis prefixes per application and environment.

Make sure production deployments have stable key management and retention policies that match your application requirements.

## Retention and purge

Retention is explicit and driver-specific. Database and Redis drivers support retention-oriented behavior; in-memory state disappears with the process.

Redis memory keeps the logical `retention_until` value in the stored payload and, when `memory.redis.retention_days` is configured, writes the Redis key with native expiration:

~~~php
'memory' => [
    'redis' => [
        'retention_days' => 30,
    ],
],
~~~

The Redis TTL is derived from `conversation.updatedAt + retention_days`. If a saved conversation is already past its computed expiration, Agent Kit writes a minimum one-second TTL. When Redis retention is `null`, keys are written without native expiration.

Lazy expiration remains in place as a safety net. If an expired Redis payload is read before Redis removes the key, `find()` deletes it and returns `null`; `purgeExpired()` also continues to scan and remove expired payloads.

Use retention defaults deliberately and document any application-specific purge expectations.

## No-store and privacy

Some workflows should not persist conversation content. Keep no-store behavior explicit through request options, runtime metadata, or workflow configuration where applicable.

Do not put secrets into conversation metadata. Telemetry should see keys and safe identifiers, not raw content.

## Attachments and legacy conversations

Optional attachment replay and Laravel AI legacy table fallback are advanced database-memory features. Redis memory still stores attachment replay payloads inside the conversation payload when they are present, so leave Redis encryption enabled when attachment references or serialized attachment payloads may be sensitive.

Enable legacy table fallback only when your application needs continued conversations across that database surface and you have reviewed the privacy implications.

## Testing memory

Use package fakes or the in-memory driver for deterministic tests. Do not depend on Redis, database persistence, or live providers unless the test is specifically validating infrastructure integration.

See [Testing](testing.md).
