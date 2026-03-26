<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Agents;

use InvalidArgumentException;

final readonly class AgentExecutionContext
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $orchestrationId,
        public string $executionId,
        public ?string $parentExecutionId,
        public AgentDefinition $agent,
        public string $providerProfile,
        public string $task,
        public array $payload = [],
        public array $metadata = [],
        public ?string $historySummary = null,
    ) {
        if ($this->orchestrationId === '') {
            throw new InvalidArgumentException('Agent execution contexts require a non-empty orchestrationId.');
        }

        if ($this->executionId === '') {
            throw new InvalidArgumentException('Agent execution contexts require a non-empty executionId.');
        }

        if ($this->parentExecutionId === '') {
            throw new InvalidArgumentException('Agent execution contexts parentExecutionId must be null or a non-empty string.');
        }

        if ($this->providerProfile === '') {
            throw new InvalidArgumentException('Agent execution contexts require a non-empty providerProfile.');
        }

        if (!in_array($this->providerProfile, $this->agent->providerProfiles(), true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Agent execution context providerProfile [%s] is not declared by agent [%s].',
                    $this->providerProfile,
                    $this->agent->key,
                ),
            );
        }

        if ($this->task === '') {
            throw new InvalidArgumentException('Agent execution contexts require a non-empty task.');
        }
    }

    public function hasParentExecution(): bool
    {
        return $this->parentExecutionId !== null;
    }

    public function payloadValue(string $key, mixed $default = null): mixed
    {
        return $this->payload[$key] ?? $default;
    }

    public function metadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
}
