<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Security\Redactor;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\OrchestrationResult;
use CreativeCrafts\LaravelAiAgentKit\Observability\Support\FailureCategory;
use CreativeCrafts\LaravelAiAgentKit\Observability\Support\FailureCategoryResolver;

final readonly class OrchestrationFailed
{
    public string $task;

    public ?string $exceptionMessage;

    public ?string $failureReason;

    public string $failureCategory;

    public function __construct(
        public string $orchestrationId,
        public string $entryAgent,
        string $task,
        public ?string $exceptionClass,
        ?string $exceptionMessage,
        public ?string $conversationId,
        ?string $failureReason = null,
        public string $status = OrchestrationResult::STATUS_FAILED,
        ?Redactor $redactor = null,
        ?string $failureCategory = null,
    ) {
        $this->task = $redactor instanceof Redactor
          ? $redactor->redactText($task)
          : $task;

        $this->exceptionMessage = is_string($exceptionMessage) && $redactor instanceof Redactor
          ? $redactor->redactText($exceptionMessage)
          : $exceptionMessage;

        $this->failureReason = is_string($failureReason) && $redactor instanceof Redactor
          ? $redactor->redactText($failureReason)
          : $failureReason;

        $this->failureCategory = $this->resolveFailureCategory($failureCategory);
    }

    private function resolveFailureCategory(?string $failureCategory): string
    {
        if (is_string($failureCategory) && $failureCategory !== '') {
            return $failureCategory;
        }

        if ($this->exceptionClass === null && $this->failureReason !== null) {
            return FailureCategoryResolver::logicalFailure();
        }

        return FailureCategory::ExecutionFailed->value;
    }
}
