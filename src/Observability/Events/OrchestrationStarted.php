<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\Redactor;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationRequest;
use CreativeCrafts\LaravelAiAgentKit\Observability\Events\Concerns\ExtractsRedactedKeys;

final readonly class OrchestrationStarted
{
    use ExtractsRedactedKeys;

    public string $task;

    /**
     * @param list<string> $inputKeys
     * @param list<string> $metadataKeys
     */
    public function __construct(
        public string $orchestrationId,
        public string $entryAgent,
        string $task,
        public array $inputKeys,
        public array $metadataKeys,
        public ?string $conversationId,
        ?Redactor $redactor = null,
    ) {
        $this->task = $redactor instanceof Redactor
          ? $redactor->redactText($task)
          : $task;
    }

    public static function fromRequest(string $orchestrationId, OrchestrationRequest $request, ?Redactor $redactor = null): self
    {
        return new self(
            orchestrationId: $orchestrationId,
            entryAgent: $request->entryAgent,
            task: $request->task,
            inputKeys: self::keys($request->input, $redactor),
            metadataKeys: self::keys($request->metadata, $redactor),
            conversationId: $request->conversationId?->toString(),
            redactor: $redactor,
        );
    }
}
