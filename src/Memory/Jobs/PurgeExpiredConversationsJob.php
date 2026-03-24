<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Memory\Jobs;

use CreativeCrafts\LaravelAiAgentKit\Memory\RetentionPurgeService;
use DateMalformedStringException;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PurgeExpiredConversationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public ?int $timeout = 120;

    public function __construct(
        public readonly ?string $purgeAtIso8601 = null,
    ) {
    }

    /**
     * @throws DateMalformedStringException
     */
    public function handle(RetentionPurgeService $retentionPurgeService): void
    {
        $retentionPurgeService->purge($this->purgeAtDateTime());
    }

    public function purgeAt(): ?string
    {
        return $this->purgeAtIso8601;
    }

    /**
     * @throws DateMalformedStringException
     */
    private function purgeAtDateTime(): ?DateTimeImmutable
    {
        if ($this->purgeAtIso8601 === null) {
            return null;
        }

        return new DateTimeImmutable($this->purgeAtIso8601);
    }
}
