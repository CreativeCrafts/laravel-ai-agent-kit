<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Tests\Fakes;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineStep;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\QueuedPipelineDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Pipeline;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\PipelineBuilder;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use RuntimeException;

final class FailingQueuedPipelineDefinition implements QueuedPipelineDefinition
{
    public function build(): Pipeline
    {
        return PipelineBuilder::make()
          ->addStep(
              new class () implements PipelineStep {
                public function handle(RunContext $context): RunContext
                {
                    throw new RuntimeException('Queued step failure');
                }
            },
          )
          ->build();
    }
}
