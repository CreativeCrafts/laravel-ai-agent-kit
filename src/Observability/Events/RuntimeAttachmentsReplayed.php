<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when persisted conversation attachments are excluded from replay by policy.
 *
 * Payload is redacted: attachment types and exclusion reasons only (no URLs or base64).
 */
final class RuntimeAttachmentsReplayed
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param list<array{type: ?string, reason: string}> $exclusions
     */
    public function __construct(
        public string $runId,
        public int $excludedCount,
        public int $includedCount,
        public array $exclusions,
    ) {
    }
}
