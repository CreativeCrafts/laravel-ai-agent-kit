<?php

namespace CreativeCrafts\LaravelAiAgentKit\Core\Pipeline;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineStep;

final readonly class Pipeline
{
    /**
     * @param  list<PipelineStep>  $steps
     */
    public function __construct(
        private array $steps,
    ) {}

    /**
     * @return list<PipelineStep>
     */
    public function steps(): array
    {
        return $this->steps;
    }
}
