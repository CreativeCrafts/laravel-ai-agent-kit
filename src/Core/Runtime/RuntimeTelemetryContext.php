<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Runtime;

use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use CreativeCrafts\LaravelAiAgentKit\Memory\ConversationId;

final readonly class RuntimeTelemetryContext
{
    /**
     * @param list<string> $requestedToolNames
     * @param list<string> $inputKeys
     * @param list<string> $metadataKeys
     */
    public function __construct(
        public string $runId,
        public array $requestedToolNames,
        public array $inputKeys,
        public array $metadataKeys,
        public ?ConversationId $packageConversationId,
        public bool $storeConversation,
        public bool $continueConversation,
        public int $projectedMessageCount,
    ) {
    }

    public static function fromRequest(ExecutionRequest $request, ProjectedConversationContext $projectedConversation): self
    {
        return new self(
            runId: $request->runId,
            requestedToolNames: self::normalizeStringList($request->toolNames),
            inputKeys: self::keys($request->input),
            metadataKeys: self::keys($request->metadata),
            packageConversationId: $projectedConversation->context instanceof RunContext
            ? $projectedConversation->context->conversationId
            : $request->conversationId,
            storeConversation: $request->storeConversation,
            continueConversation: $request->continueConversation,
            projectedMessageCount: $projectedConversation->projectedMessageCount(),
        );
    }

    /**
     * Placeholder context for telemetry agent constructors when no runtime
     * request context is available. Production execution paths should always
     * supply a context from {@see fromRequest()}.
     */
    public static function unspecified(): self
    {
        return new self(
            runId: '',
            requestedToolNames: [],
            inputKeys: [],
            metadataKeys: [],
            packageConversationId: null,
            storeConversation: false,
            continueConversation: false,
            projectedMessageCount: 0,
        );
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private static function normalizeStringList(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            if ($value === '') {
                continue;
            }
            if (in_array($value, $normalized, true)) {
                continue;
            }
            $normalized[] = $value;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $values
     * @return list<string>
     */
    private static function keys(array $values): array
    {
        return array_values(
            array_filter(
                array_keys($values),
                static fn (string $key): bool => $key !== '',
            ),
        );
    }
}
