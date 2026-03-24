<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Commands;

use CreativeCrafts\LaravelAiAgentKit\Memory\Exceptions\ConversationRetentionPurgeException;
use CreativeCrafts\LaravelAiAgentKit\Memory\Jobs\PurgeExpiredConversationsJob;
use CreativeCrafts\LaravelAiAgentKit\Memory\RetentionPurgeService;
use DateMalformedStringException;
use DateTimeImmutable;
use Illuminate\Console\Command;

final class PurgeConversationsCommand extends Command
{
    protected $signature = 'ai:purge:conversations
        {--queued : Dispatch the retention purge through the queue instead of running synchronously}
        {--connection= : Queue connection to use when the purge is queued}
        {--queue= : Queue name to use when the purge is queued}
        {--at= : Optional ISO-8601 timestamp to evaluate retention against}';

    protected $description = 'Purge expired AI agent conversations according to the configured retention policy.';

    public function handle(RetentionPurgeService $retentionPurgeService): int
    {
        try {
            $purgeAt = $this->purgeAt();
        } catch (DateMalformedStringException) {
            $this->components->error('The [--at] option must be a valid ISO-8601 datetime string.');

            return self::FAILURE;
        }

        if ($this->option('queued')) {
            $job = new PurgeExpiredConversationsJob($purgeAt?->format(DATE_ATOM));

            $connection = $this->option('connection');
            if (is_string($connection) && $connection !== '') {
                $job->onConnection($connection);
            }

            $queue = $this->option('queue');
            if (is_string($queue) && $queue !== '') {
                $job->onQueue($queue);
            }

            dispatch($job);

            $this->components->info('Conversation retention purge job dispatched successfully.');

            return self::SUCCESS;
        }

        try {
            $purgedCount = $retentionPurgeService->purge($purgeAt);
        } catch (ConversationRetentionPurgeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Purged %d expired conversation(s).', $purgedCount));

        return self::SUCCESS;
    }

    /**
     * @throws DateMalformedStringException
     */
    private function purgeAt(): ?DateTimeImmutable
    {
        $purgeAt = $this->option('at');

        if (!is_string($purgeAt) || trim($purgeAt) === '') {
            return null;
        }

        return new DateTimeImmutable(trim($purgeAt));
    }
}
