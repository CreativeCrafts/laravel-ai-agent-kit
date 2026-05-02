<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Pipeline;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineResultHandler;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\QueuedPipelineDefinition;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\QueuedPipelineDispatcher;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Exceptions\InvalidPipelineResultHandlerException;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Exceptions\InvalidQueuedPipelineDefinitionException;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Jobs\RunQueuedPipelineJob;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use RuntimeException;

final class LaravelQueuedPipelineDispatcher implements QueuedPipelineDispatcher
{
    public function __construct(
        private ConfigRepository $config,
    ) {
    }

    /**
     * @param class-string $pipelineDefinition
     * @param class-string|null $resultHandler
     */
    public function dispatch(
        string $pipelineDefinition,
        RunContext $context,
        QueueDispatchOptions $options = new QueueDispatchOptions(),
        ?string $resultHandler = null,
    ): void {
        if (!class_exists($pipelineDefinition) || !is_subclass_of($pipelineDefinition, QueuedPipelineDefinition::class)) {
            throw InvalidQueuedPipelineDefinitionException::forClass($pipelineDefinition);
        }

        if ($resultHandler !== null && (!class_exists($resultHandler) || !is_subclass_of($resultHandler, PipelineResultHandler::class))) {
            throw InvalidPipelineResultHandlerException::forClass($resultHandler);
        }

        $job = new RunQueuedPipelineJob(
            pipelineDefinition: $pipelineDefinition,
            context: $context,
            resultHandler: $resultHandler,
            options: $options,
        );

        $this->assertQueuedJobSizeIfDebugging($job);

        dispatch($job);
    }

    private function assertQueuedJobSizeIfDebugging(RunQueuedPipelineJob $job): void
    {
        $block = $this->config->get('ai-agent-kit.pipeline.queued', []);

        if (!is_array($block)) {
            return;
        }

        $guard = (bool)($block['debug_payload_guard'] ?? false);

        if (!$guard || !$this->config->get('app.debug')) {
            return;
        }

        $maxBytes = $block['max_serialized_job_bytes'] ?? 524288;

        if (!is_int($maxBytes) || $maxBytes < 1) {
            return;
        }

        $size = strlen(serialize($job));

        if ($size > $maxBytes) {
            throw new RuntimeException(sprintf(
                'Queued pipeline job serialized size (%d bytes) exceeds ai-agent-kit.pipeline.queued.max_serialized_job_bytes (%d). Reduce RunContext payload (see UPGRADE.md).',
                $size,
                $maxBytes,
            ));
        }
    }
}
