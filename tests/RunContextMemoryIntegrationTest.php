<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineRunner;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineStep;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationContextManager;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\PipelineBuilder;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessage;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessageRole;
use CreativeCrafts\LaravelAiAgentKit\Memory\MessageId;

beforeEach(function () {
    app()->singleton(ConversationStore::class, function (): ConversationStore {
        return new class () implements ConversationStore {
            /**
             * @var array<string, Conversation>
             */
            private array $conversations = [];

            public function find(ConversationId $conversationId): ?Conversation
            {
                return $this->conversations[$conversationId->toString()] ?? null;
            }

            public function save(Conversation $conversation): void
            {
                $this->conversations[$conversation->id->toString()] = $conversation;
            }

            public function delete(ConversationId $conversationId): void
            {
                unset($this->conversations[$conversationId->toString()]);
            }
        };
    });
});

it('starts a new conversation explicitly through the memory contract', function () {
    $manager = app(ConversationContextManager::class);
    $conversationId = new ConversationId('conv-start');

    $context = $manager->start(
        context: new RunContext(runId: 'run-start', input: ['prompt' => 'hello']),
        conversationId: $conversationId,
    );

    expect($context->hasConversationId())
      ->toBeTrue()
      ->and($context->conversationId?->equals($conversationId))->toBeTrue()
      ->and($context->shouldContinueConversation())->toBeFalse()
      ->and($context->shouldStoreConversation())->toBeTrue()
      ->and($context->hasConversation())->toBeTrue()
      ->and($context->conversation?->id->equals($conversationId))->toBeTrue()
      ->and($context->conversation?->messageCount())->toBe(0);
});

it('continues an existing conversation and persists appended messages through the pipeline runner', function () {
    $store = app(ConversationStore::class);
    $conversationId = new ConversationId('conv-continue');
    $startedAt = new DateTimeImmutable('2026-03-14T10:00:00+00:00');

    $store->save(
        new Conversation(
            id: $conversationId,
            createdAt: $startedAt,
            updatedAt: $startedAt,
            messages: [
          new ConversationMessage(
              id: new MessageId('msg-existing'),
              role: ConversationMessageRole::User,
              content: 'Existing message',
              createdAt: $startedAt,
          ),
        ],
        ),
    );

    $contextManager = app(ConversationContextManager::class);
    $runner = app(PipelineRunner::class);

    $context = $contextManager->continue(
        context: new RunContext(runId: 'run-continue'),
        conversationId: $conversationId,
    );

    $pipeline = PipelineBuilder::make()
      ->addStep(
          new class () implements PipelineStep {
            public function handle(RunContext $context): RunContext
            {
                $conversation = $context->conversation;

                if ($conversation === null) {
                    throw new RuntimeException('Expected conversation to be loaded.');
                }

                $nextTimestamp = new DateTimeImmutable('2026-03-14T10:05:00+00:00');

                return $context
                  ->withStateValue('loaded_message_count', $conversation->messageCount())
                  ->withConversation(
                      $conversation->withAppendedMessage(
                          new ConversationMessage(
                              id: new MessageId('msg-appended'),
                              role: ConversationMessageRole::Assistant,
                              content: 'Follow-up message',
                              createdAt: $nextTimestamp,
                          ),
                      ),
                  )
                  ->incrementStepCount();
            }
        },
      )
      ->build();

    $result = $runner->run($pipeline, $context);
    $persistedConversation = $store->find($conversationId);

    expect($result->stateValue('loaded_message_count'))
      ->toBe(1)
      ->and($result->conversation?->messageCount())->toBe(2)
      ->and($result->conversation?->latestMessage()?->content)->toBe('Follow-up message')
      ->and($persistedConversation?->messageCount())->toBe(2)
      ->and($persistedConversation?->latestMessage()?->content)->toBe('Follow-up message');
});

it('preserves no-store semantics for explicit conversation runs', function () {
    $store = app(ConversationStore::class);
    $manager = app(ConversationContextManager::class);
    $conversationId = new ConversationId('conv-no-store');
    $runner = app(PipelineRunner::class);

    $context = $manager->start(
        context: new RunContext(runId: 'run-no-store'),
        conversationId: $conversationId,
        storeConversation: false,
    );

    $pipeline = PipelineBuilder::make()
      ->addStep(
          new class () implements PipelineStep {
            public function handle(RunContext $context): RunContext
            {
                $conversation = $context->conversation;

                if ($conversation === null) {
                    throw new RuntimeException('Expected conversation to be initialized.');
                }

                return $context
                  ->withConversation(
                      $conversation->withAppendedMessage(
                          new ConversationMessage(
                              id: new MessageId('msg-no-store'),
                              role: ConversationMessageRole::User,
                              content: 'Ephemeral message',
                              createdAt: new DateTimeImmutable('2026-03-14T11:00:00+00:00'),
                          ),
                      ),
                  )
                  ->incrementStepCount();
            }
        },
      )
      ->build();

    $result = $runner->run($pipeline, $context);

    expect($result->shouldStoreConversation())
      ->toBeFalse()
      ->and($result->conversation?->messageCount())->toBe(1)
      ->and($store->find($conversationId))->toBeNull();
});
