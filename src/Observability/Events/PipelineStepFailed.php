<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Events;

use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use Throwable;

final readonly class PipelineStepFailed
{
    public function __construct(
        public string $runId,
        public string $stepClass,
        public int $stepIndex,
        public ?string $selectedProvider,
        public string $exceptionClass,
    ) {
    }

    public static function fromContext(RunContext $context, string $stepClass, int $stepIndex, Throwable $throwable): self
    {
        return new self(
            runId: $context->runId,
            stepClass: $stepClass,
            stepIndex: $stepIndex,
            selectedProvider: $context->selectedProvider,
            exceptionClass: $throwable::class,
        );
    }
}
