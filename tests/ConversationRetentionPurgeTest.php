<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Commands\PurgeConversationsCommand;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationRetentionPurger;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Memory\ConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\Conversation;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessage;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationMessageRole;
use CreativeCrafts\LaravelAiAgentKit\Memory\Exceptions\ConversationRetentionPurgeException;
use CreativeCrafts\LaravelAiAgentKit\Memory\InMemoryConversationStore;
use CreativeCrafts\LaravelAiAgentKit\Memory\Jobs\PurgeExpiredConversationsJob;
use CreativeCrafts\LaravelAiAgentKit\Memory\MessageId;
use CreativeCrafts\LaravelAiAgentKit\Memory\RetentionPurgeService;
use Illuminate\Console\Command as ConsoleCommand;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    config()->set('ai-agent-kit.memory.default_driver', 'in_memory');
    config()->set('ai-agent-kit.memory.in_memory.retention_days', 30);

    forgetResolvedRetentionPurgeServices();
});

it('registers the retention purge command', function (): void {
    expect($this->app->make(PurgeConversationsCommand::class))->toBeInstanceOf(PurgeConversationsCommand::class);
});

it('runs the retention purge command synchronously against the configured purger', function (): void {
    $store = seededRetentionPurgeStore();

    $this->artisan('ai:purge:conversations', [
      '--at' => '2026-03-01T00:00:00+00:00',
    ])->assertSuccessful();

    expect($store->find(new ConversationId('conv-expired-retention')))
      ->toBeNull()
      ->and($store->find(new ConversationId('conv-active-retention')))->toBeInstanceOf(Conversation::class);
});

it('dispatches the retention purge as a queueable job when requested', function (): void {
    Queue::fake();

    $this->artisan('ai:purge:conversations', [
      '--queued' => true,
      '--connection' => 'redis',
      '--queue' => 'ai-maintenance',
      '--at' => '2026-03-01T00:00:00+00:00',
    ])->assertSuccessful();

    Queue::assertPushed(PurgeExpiredConversationsJob::class, function (PurgeExpiredConversationsJob $job): bool {
        return $job->connection === 'redis'
          && $job->queue === 'ai-maintenance'
          && $job->purgeAt() === '2026-03-01T00:00:00+00:00';
    });
});

it('executes the queued purge job through the retention purge service', function (): void {
    $store = seededRetentionPurgeStore();
    $job = new PurgeExpiredConversationsJob('2026-03-01T00:00:00+00:00');

    $job->handle(app(RetentionPurgeService::class));

    expect($store->find(new ConversationId('conv-expired-retention')))
      ->toBeNull()
      ->and($store->find(new ConversationId('conv-active-retention')))->toBeInstanceOf(Conversation::class);
});

it('wraps purge failures in a typed retention purge exception', function (): void {
    $service = new RetentionPurgeService(
        purger: new class () implements ConversationRetentionPurger {
          public function purgeExpired(?DateTimeImmutable $now = null): int
          {
              throw new RuntimeException('purge-failed');
          }
      },
        memoryDriver: 'in_memory',
    );

    expect(fn (): int => $service->purge())
      ->toThrow(ConversationRetentionPurgeException::class, 'Conversation retention purge failed for memory driver [in_memory].');
});

it('fails with a clear message when the purge timestamp is invalid', function (): void {
    $this->artisan('ai:purge:conversations', [
      '--at' => 'not-a-datetime',
    ])->assertExitCode(ConsoleCommand::FAILURE);
});

function seededRetentionPurgeStore(): InMemoryConversationStore
{
    /** @var InMemoryConversationStore $store */
    $store = app(ConversationStore::class);

    $store->save(
        new Conversation(
            id: new ConversationId('conv-expired-retention'),
            createdAt: new DateTimeImmutable('2026-01-01T07:00:00+00:00'),
            updatedAt: new DateTimeImmutable('2026-01-01T08:00:00+00:00'),
            messages: [
          new ConversationMessage(
              id: new MessageId('msg-expired-retention'),
              role: ConversationMessageRole::User,
              content: 'Expired content',
              createdAt: new DateTimeImmutable('2026-01-01T08:00:00+00:00'),
          ),
        ],
        ),
    );

    $store->save(
        new Conversation(
            id: new ConversationId('conv-active-retention'),
            createdAt: new DateTimeImmutable('2026-03-14T07:00:00+00:00'),
            updatedAt: new DateTimeImmutable('2026-03-14T08:00:00+00:00'),
            messages: [
          new ConversationMessage(
              id: new MessageId('msg-active-retention'),
              role: ConversationMessageRole::Assistant,
              content: 'Active content',
              createdAt: new DateTimeImmutable('2026-03-14T08:00:00+00:00'),
          ),
        ],
        ),
    );

    return $store;
}

function forgetResolvedRetentionPurgeServices(): void
{
    app()->forgetInstance(ConversationStore::class);
    app()->forgetInstance(ConversationRetentionPurger::class);
    app()->forgetInstance(InMemoryConversationStore::class);
    app()->forgetInstance(RetentionPurgeService::class);
}
