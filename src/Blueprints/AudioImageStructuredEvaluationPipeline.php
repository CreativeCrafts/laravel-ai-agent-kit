<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Blueprints;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\QueuedPipelineDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Pipeline;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\PipelineBuilder;

final readonly class AudioImageStructuredEvaluationPipeline implements QueuedPipelineDefinition
{
    public function __construct(
        private AudioImageStructuredEvaluationPipelineStep $step,
    ) {
    }

    public function build(): Pipeline
    {
        return PipelineBuilder::make()
            ->addStep($this->step)
            ->build();
    }
}
