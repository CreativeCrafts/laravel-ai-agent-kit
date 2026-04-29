<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * Redacted streaming completion (lengths and identifiers only).
 *
 * @param list<string> $requestedToolNames
 * @param list<string> $metadataKeys
 */
final class RuntimeStreamCompleted implements ShouldBroadcast
{
    use InteractsWithSockets;

    /**
     * @param list<string> $requestedToolNames
     * @param list<string> $metadataKeys
     */
    public function __construct(
        public string $runId,
        public string $invocationId,
        public string $provider,
        public string $model,
        public array $requestedToolNames,
        public array $metadataKeys,
        public ?string $packageConversationId,
        public int $projectedMessageCount,
        public int $promptTokens,
        public int $completionTokens,
        public int $totalTokens,
        public int $outputLength,
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
        return 'runtime.stream.completed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'run_id' => $this->runId,
            'invocation_id' => $this->invocationId,
            'provider' => $this->provider,
            'model' => $this->model,
            'requested_tool_names' => $this->requestedToolNames,
            'metadata_keys' => $this->metadataKeys,
            'package_conversation_id' => $this->packageConversationId,
            'projected_message_count' => $this->projectedMessageCount,
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
            'output_length' => $this->outputLength,
        ];
    }
}
