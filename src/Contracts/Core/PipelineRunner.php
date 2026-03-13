<?php

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Core;

use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Pipeline;
use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;

interface PipelineRunner
{
    public function run(Pipeline $pipeline, RunContext $context): RunContext;
}
