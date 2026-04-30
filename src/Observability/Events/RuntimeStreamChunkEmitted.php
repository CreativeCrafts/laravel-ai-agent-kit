<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * Redacted streaming chunk notification (no prompt or full model text).
 *
 * When {@see $broadcastChannel} is non-null and non-empty, the event is broadcast on that channel.
 */
final class RuntimeStreamChunkEmitted implements ShouldBroadcast
{
    use InteractsWithSockets;

    public function __construct(
        public string $runId,
        public int $sequence,
        public string $type,
        public int $deltaLength,
        public ?string $messageId = null,
        private readonly ?string $broadcastChannel = null,
    ) {
    }

    public function broadcastWhen(): bool
    {
        return $this->broadcastChannel !== null && $this->broadcastChannel !== '';
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        if ($this->broadcastChannel === null || $this->broadcastChannel === '') {
            return [];
        }

        return [new Channel($this->broadcastChannel)];
    }

    public function broadcastAs(): string
    {
        return 'runtime.stream.chunk';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'run_id' => $this->runId,
            'sequence' => $this->sequence,
            'type' => $this->type,
            'delta_length' => $this->deltaLength,
            'message_id' => $this->messageId,
        ];
    }
}
