<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Core;

use CreativeCrafts\LaravelAiAgentKit\Core\Pipeline\Pipeline;

interface QueuedPipelineDefinition
{
    public function build(): Pipeline;
}
