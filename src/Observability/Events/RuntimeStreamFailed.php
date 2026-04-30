<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\Redactor;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\Concerns\ExtractsRedactedKeys;
use CreativeCrafts\LaravelAiAgentKit\Observability\Support\FailureCategoryResolver;
use Throwable;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * Redacted terminal streaming failure (no prompt text).
 */
final class RuntimeStreamFailed implements ShouldBroadcast
{
    use ExtractsRedactedKeys;
    use InteractsWithSockets;

    /**
     * @param list<string> $requestedToolNames
     * @param list<string> $inputKeys
     * @param list<string> $metadataKeys
     */
    public function __construct(
        public string $runId,
        public ?string $provider,
        public ?string $model,
        public array $requestedToolNames,
        public array $inputKeys,
        public array $metadataKeys,
        public ?string $packageConversationId,
        public bool $storeConversation,
        public bool $continueConversation,
        public int $projectedMessageCount,
        public string $failureCategory,
        public string $exceptionClass,
        public ?string $exceptionMessage,
        private readonly ?string $broadcastChannel = null,
    ) {
    }

    public static function fromRequest(
        ExecutionRequest $request,
        Throwable $throwable,
        int $projectedMessageCount = 0,
        ?string $packageConversationId = null,
        ?Redactor $redactor = null,
        ?string $broadcastChannel = null,
    ): self {
        return new self(
            runId: $request->runId,
            provider: $request->provider,
            model: $request->model,
            requestedToolNames: $request->toolNames,
            inputKeys: self::keys($request->input, $redactor),
            metadataKeys: self::keys($request->metadata, $redactor),
            packageConversationId: $packageConversationId ?? $request->conversationId?->toString(),
            storeConversation: $request->storeConversation,
            continueConversation: $request->continueConversation,
            projectedMessageCount: $projectedMessageCount,
            failureCategory: FailureCategoryResolver::forThrowable($throwable),
            exceptionClass: $throwable::class,
            exceptionMessage: self::redactedExceptionMessage($throwable->getMessage(), $redactor),
            broadcastChannel: $broadcastChannel,
        );
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
        return 'runtime.stream.failed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'run_id' => $this->runId,
            'provider' => $this->provider,
            'model' => $this->model,
            'requested_tool_names' => $this->requestedToolNames,
            'input_keys' => $this->inputKeys,
            'metadata_keys' => $this->metadataKeys,
            'package_conversation_id' => $this->packageConversationId,
            'store_conversation' => $this->storeConversation,
            'continue_conversation' => $this->continueConversation,
            'projected_message_count' => $this->projectedMessageCount,
            'failure_category' => $this->failureCategory,
            'exception_class' => $this->exceptionClass,
            'exception_message' => $this->exceptionMessage,
        ];
    }

    private static function redactedExceptionMessage(string $message, ?Redactor $redactor = null): ?string
    {
        if ($message === '') {
            return null;
        }

        return $redactor instanceof Redactor
          ? $redactor->redactText($message)
          : $message;
    }
}
