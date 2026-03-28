<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\Redactor;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\ExecutionTraceRecord;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\Concerns\ExtractsRedactedKeys;

final readonly class OrchestrationDelegated
{
    use ExtractsRedactedKeys;

    /**
     * @param list<string> $traceMetadataKeys
     */
    public function __construct(
        public string $orchestrationId,
        public string $executionId,
        public ?string $parentExecutionId,
        public string $agentKey,
        public string $providerProfile,
        public string $targetAgent,
        public string $delegationMode,
        public string $policyMode,
        public bool $policyRewritten,
        public ?string $proposedTargetAgent,
        public array $traceMetadataKeys,
    ) {
    }

    public static function fromTrace(ExecutionTraceRecord $trace, ?Redactor $redactor = null): self
    {
        return new self(
            orchestrationId: $trace->orchestrationId,
            executionId: $trace->executionId,
            parentExecutionId: $trace->parentExecutionId,
            agentKey: $trace->agentKey,
            providerProfile: $trace->providerProfile,
            targetAgent: $trace->targetAgent ?? '[missing-target]',
            delegationMode: self::stringMetadata($trace, 'delegation_mode'),
            policyMode: self::stringMetadata($trace, 'policy_mode'),
            policyRewritten: self::boolMetadata($trace, 'policy_rewritten'),
            proposedTargetAgent: self::nullableStringMetadata($trace, 'proposed_target_agent'),
            traceMetadataKeys: self::keys($trace->metadata, $redactor),
        );
    }

    private static function stringMetadata(ExecutionTraceRecord $trace, string $key): string
    {
        $value = $trace->metadata[$key] ?? null;

        return is_string($value) && $value !== ''
          ? $value
          : '[unknown]';
    }

    private static function boolMetadata(ExecutionTraceRecord $trace, string $key): bool
    {
        return ($trace->metadata[$key] ?? false) === true;
    }

    private static function nullableStringMetadata(ExecutionTraceRecord $trace, string $key): ?string
    {
        $value = $trace->metadata[$key] ?? null;

        return is_string($value) && $value !== ''
          ? $value
          : null;
    }
}
