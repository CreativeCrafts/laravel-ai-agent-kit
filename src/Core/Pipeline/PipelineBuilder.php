<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Pipeline;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Core\PipelineStep;

final readonly class PipelineBuilder
{
    /**
     * @param  list<PipelineStep>  $steps
     */
    private function __construct(
        private array $steps = [],
    ) {
    }

    public static function make(): self
    {
        return new self();
    }

    public function addStep(PipelineStep $step): self
    {
        $steps = $this->steps;
        $steps[] = $step;

        return new self($steps);
    }

    /**
     * @param  iterable<PipelineStep>  $steps
     */
    public function addSteps(iterable $steps): self
    {
        $nextSteps = $this->steps;

        foreach ($steps as $step) {
            $nextSteps[] = $step;
        }

        return new self($nextSteps);
    }

    public function build(): Pipeline
    {
        return new Pipeline($this->steps);
    }
}
