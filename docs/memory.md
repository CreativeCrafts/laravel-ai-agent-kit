# Memory

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
- `database`: persistent storage with encrypted payload support and retention behavior.
- `redis`: shared ephemeral memory across workers without database persistence.

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

Make sure production deployments have stable key management and retention policies that match your application requirements.

## Retention and purge

Retention is explicit and driver-specific. Database and Redis drivers support retention-oriented behavior; in-memory state disappears with the process.

Use retention defaults deliberately and document any application-specific purge expectations.

## No-store and privacy

Some workflows should not persist conversation content. Keep no-store behavior explicit through request options, runtime metadata, or workflow configuration where applicable.

Do not put secrets into conversation metadata. Telemetry should see keys and safe identifiers, not raw content.

## Attachments and legacy conversations

Optional attachment replay and Laravel AI legacy table fallback are advanced database-memory features. Enable them only when your application needs continued conversations across those surfaces and you have reviewed the privacy implications.

## Testing memory

Use package fakes or the in-memory driver for deterministic tests. Do not depend on Redis, database persistence, or live providers unless the test is specifically validating infrastructure integration.

See [Testing](testing.md).
