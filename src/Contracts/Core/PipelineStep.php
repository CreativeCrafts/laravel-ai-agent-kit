<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Core;

use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;

interface PipelineStep
{
    public function handle(RunContext $context): RunContext;
}
