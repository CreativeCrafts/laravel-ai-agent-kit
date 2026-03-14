<?php

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Core;

use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\RunContext;
use Throwable;

interface PipelineResultHandler
{
    public function handleSuccess(RunContext $context): void;

    public function handleFailure(RunContext $context, Throwable $throwable): void;
}
