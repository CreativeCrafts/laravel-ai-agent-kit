# Pipelines, queues, memory, and vectors

This guide covers synchronous and queued pipelines, conversation memory with `RunContext`, vector storage, and how they compose with runtime-integrated memory.

## Synchronous pipeline

Build and run a synchronous pipeline with typed steps through dependency injection:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineRunner;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineStep;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\PipelineBuilder;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;

final class NormalizePipelineService
{
    public function __construct(
        private PipelineRunner $runner,
    ) {
    }

    public function run(): RunContext
    {
        $pipeline = PipelineBuilder::make()
            ->addStep(new class implements PipelineStep
            {
                public function handle(RunContext $context): RunContext
                {
                    return $context
                        ->withStateValue('normalized', true)
                        ->incrementStepCount();
                }
            })
            ->build();

        return $this->runner->run(
            $pipeline,
            new RunContext(
                runId: 'run-001',
                input: ['text' => 'Hello world'],
            ),
        );
    }
}
~~~

## Queued pipeline

Dispatch a queued pipeline using a typed pipeline definition and injected dispatcher:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineResultHandler;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineStep;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\QueuedPipelineDefinition;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\QueuedPipelineDispatcher;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Pipeline;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\PipelineBuilder;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\QueueDispatchOptions;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use Throwable;

final class NormalizeTranscriptPipeline implements QueuedPipelineDefinition
{
    public function build(): Pipeline
    {
        return PipelineBuilder::make()
            ->addStep(new class implements PipelineStep
            {
                public function handle(RunContext $context): RunContext
                {
                    return $context
                        ->withStateValue('normalized', true)
                        ->incrementStepCount();
                }
            })
            ->build();
    }
}

final class PersistPipelineResult implements PipelineResultHandler
{
    public function handleSuccess(RunContext $context): void
    {
        // Persist or publish the final pipeline state.
    }

    public function handleFailure(RunContext $context, Throwable $throwable): void
    {
        report($throwable);
    }
}

final class QueueTranscriptNormalizationCommand
{
    public function __construct(
        private QueuedPipelineDispatcher $dispatcher,
    ) {
    }

    public function __invoke(): void
    {
        $this->dispatcher->dispatch(
            definition: new NormalizeTranscriptPipeline(),
            context: new RunContext(
                runId: 'queued-run-001',
                input: ['text' => 'Queued pipeline input'],
            ),
            handler: new PersistPipelineResult(),
            options: new QueueDispatchOptions(
                queue: 'ai-pipelines',
                connection: 'sync',
            ),
        );
    }
}
~~~

For serialized `RunContext` fields and the optional debug payload guard, see [Configuration reference — Queued pipelines and `RunContext`](configuration.md#queued-pipelines-and-runcontext).

## Conversation memory

Use conversation memory through injected package-owned context manager and store contracts:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationContextManager;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
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
        private ConversationContextManager $contextManager,
    ) {
    }

    public function appendUserMessage(): void
    {
        $conversationId = new ConversationId('conv-001');
        $timestamp = new DateTimeImmutable();

        $conversation = $this->store->find($conversationId);

        if ($conversation === null) {
            $conversation = new Conversation(
                id: $conversationId,
                createdAt: $timestamp,
                updatedAt: $timestamp,
            );
        }

        $conversation = $conversation->withAppendedMessage(
            new ConversationMessage(
                id: new MessageId('msg-001'),
                role: ConversationMessageRole::User,
                content: 'Please summarize my refund options.',
                createdAt: $timestamp,
                metadata: ['channel' => 'support'],
            ),
            $timestamp,
        );

        $this->store->save($conversation);
    }

    public function initializeRunContext(RunContext $context): RunContext
    {
        return $this->contextManager->initialize($context);
    }
}
~~~

For runtime-integrated memory (blueprints, `AiRuntime` with `store_conversation` / `continue_conversation`), use `ConversationContextManager` and the `RunContext` pipeline — see the blueprint tests under `tests/`.

## Vector storage

Use vector storage through the injected package contract to keep embeddings and semantic search behind a stable boundary. Set `vector.default_driver` to `in_memory` (default, ephemeral), **`database`** for SQLite/MySQL/PostgreSQL persistence via `ai_agent_vector_documents` (publish migrations), or bind a custom `VectorStoreInterface` implementation (for example Pinecone or pgvector).

~~~php
use CreativeCrafts\LaravelAiAgentKit\Contracts\Vector\VectorStoreInterface;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorDocument;
use CreativeCrafts\LaravelAiAgentKit\Vector\VectorSearchQuery;

final class SupportKnowledgeService
{
    public function __construct(
        private VectorStoreInterface $vectorStore,
    ) {
    }

    public function indexAndSearch(): array
    {
        $this->vectorStore->upsert('support', [
            new VectorDocument(
                id: 'doc-001',
                embedding: [0.8, 0.2, 0.1],
                metadata: ['topic' => 'refunds'],
            ),
        ]);

        return $this->vectorStore->search(
            'support',
            new VectorSearchQuery(
                embedding: [0.9, 0.1, 0.0],
                limit: 3,
                filter: ['topic' => 'refunds'],
            ),
        );
    }
}
~~~

Built-in vector stores enforce **one embedding width per namespace**; see [Configuration reference — Vector embeddings](configuration.md#vector-embeddings-built-in-stores).

## See also

- [Orchestration and blueprints](orchestration-and-blueprints.md)
- [Testing with fakes](testing-with-fakes.md)
