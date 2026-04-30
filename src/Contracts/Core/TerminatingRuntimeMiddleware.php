<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Core;

use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;

/**
 * Optional hook run after a successful execution, in reverse registration order (outer middleware last).
 */
interface TerminatingRuntimeMiddleware extends RuntimeMiddleware
{
    public function terminate(ExecutionRequest $request, ExecutionResult $result): void;
}
