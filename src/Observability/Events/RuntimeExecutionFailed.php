<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\Redactor;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\Concerns\ExtractsRedactedKeys;
use CreativeCrafts\LaravelAiAgentKit\Observability\Support\FailureCategoryResolver;
use Throwable;

final readonly class RuntimeExecutionFailed
{
    use ExtractsRedactedKeys;

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
    ) {
    }

    public static function fromRequest(
        ExecutionRequest $request,
        Throwable $throwable,
        int $projectedMessageCount = 0,
        ?string $packageConversationId = null,
        ?Redactor $redactor = null,
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
        );
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
