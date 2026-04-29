<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Contracts\Core;

use Closure;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionRequest;
use CreativeCrafts\LaravelAiAgentKit\Core\Runtime\ExecutionResult;

/**
 * Middleware invoked around every {@see AiRuntime::execute} dispatch (direct, blueprint, orchestration).
 */
interface RuntimeMiddleware
{
    /**
     * @param Closure(ExecutionRequest): ExecutionResult $next
     */
    public function handle(ExecutionRequest $request, Closure $next): ExecutionResult;
}
